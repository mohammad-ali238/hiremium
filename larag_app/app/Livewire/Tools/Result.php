<?php

namespace App\Livewire\Tools;

use App\Jobs\Tools\RunToolStagesJob;
use App\Models\ToolRun;
use App\Services\Tools\Contracts\ToolDefinition;
use App\Services\Tools\ToolQuota;
use App\Services\Tools\ToolRegistry;
use App\Services\Tools\ToolRunner;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * /tools/result/{id}/{uuid} — the public, shareable result page.
 *
 * Opening this page is the moment everything happens: it proves the email
 * address is real, makes the link permanent, and starts the run. Each stage is
 * its own Livewire request, so the progress bar moves as the work happens
 * instead of after it; a delayed queued job finishes the run if the visitor
 * closes the tab.
 */
class Result extends Component
{
    /**
     * Which run this page is showing.
     *
     * Locked, and that is load-bearing: mount() proves the id and the uuid
     * belong to the same row, but every later request re-hydrates these from
     * the browser. Unlocked, a visitor could point the component at somebody
     * else's run and read their report.
     */
    #[Locked]
    public int $runId = 0;

    #[Locked]
    public string $uuid = '';

    /** Never opened inside its window — the link is dead and nothing ran. */
    #[Locked]
    public bool $expired = false;

    /** The site-wide daily ceiling is full; this link is still good tomorrow. */
    #[Locked]
    public bool $busy = false;

    public function mount(ToolRunner $runner, int $id, string $uuid): void
    {
        // Both halves of the URL must point at the same row.
        $run = ToolRun::query()->where('id', $id)->where('uuid', $uuid)->first();

        abort_if($run === null, 404);

        $this->runId = $run->id;
        $this->uuid = $run->uuid;

        if ($run->isExpired()) {
            if ($run->status !== ToolRun::STATUS_EXPIRED) {
                $run->forceFill(['status' => ToolRun::STATUS_EXPIRED])->save();
            }

            $this->expired = true;

            return;
        }

        // A GET is not a person. Mail security scanners, link previews and
        // browser prefetchers all fetch this URL before the visitor has seen
        // the email — starting the AI here would spend money on a machine, and
        // would clear the expiry of a link nobody ever read. So a bare load
        // only ever looks; the run begins in start(), behind a click.
        if ($run->opened_at !== null) {
            $runner->recordView($run);
        }
    }

    /**
     * "See my result" — the first thing in this flow a scanner cannot do.
     *
     * This click is what actually proves a person is holding the link, which
     * is the job the email round trip was supposed to do on its own.
     */
    public function start(ToolRunner $runner): void
    {
        $run = $this->run();

        if ($run->isExpired()) {
            $run->forceFill(['status' => ToolRun::STATUS_EXPIRED])->save();
            $this->expired = true;
            $this->forget();

            return;
        }

        if ($run->status !== ToolRun::STATUS_AWAITING_OPEN) {
            return;
        }

        // The ceiling is on work done, so it is enforced where the work starts —
        // and the check and the claim are taken together, or two people pressing
        // at once would both pass the last free slot. The run stays
        // awaiting_open when refused: the link keeps its place and the visitor
        // can simply come back and press again.
        try {
            $claimed = app(ToolQuota::class)->decide(function () use ($run, $runner): bool {
                if (app(ToolQuota::class)->siteCapReached()) {
                    return false;
                }

                $runner->open($run, request()->ip());

                $run->forceFill([
                    'status' => ToolRun::STATUS_PROCESSING,
                    'processing_started_at' => now(),
                ])->save();

                return true;
            });
        } catch (LockTimeoutException) {
            $claimed = false;
        }

        if (! $claimed) {
            $this->busy = true;

            return;
        }

        $this->busy = false;

        // The safety net: if this tab goes away mid-run, a worker picks the
        // rest up. Delayed, because the tab itself is usually faster.
        RunToolStagesJob::dispatch($run->id)
            ->delay(now()->addSeconds(90))
            ->onQueue(config('hiremium.queue.name', 'cv'));

        $this->forget();
        $this->dispatch('tool-advance')->self();
    }

    /**
     * Both halves of the URL, every time — not just on mount. The lock above
     * should make that impossible to subvert; this makes it harmless if the
     * lock is ever lost in a refactor.
     */
    #[Computed]
    public function run(): ToolRun
    {
        return ToolRun::query()
            ->where('id', $this->runId)
            ->where('uuid', $this->uuid)
            ->firstOrFail();
    }

    #[Computed]
    public function tool(): ?ToolDefinition
    {
        return app(ToolRegistry::class)->find($this->run()->tool);
    }

    /** @return list<array{key: string, label: string, icon: string, state: string}> */
    #[Computed]
    public function steps(): array
    {
        return app(ToolRunner::class)->steps($this->run());
    }

    #[Computed]
    public function report(): array
    {
        return (array) $this->run()->output;
    }

    /** The shareable address of this page — also what Copy link puts on the clipboard. */
    #[Computed]
    public function shareUrl(): string
    {
        return $this->run()->publicUrl();
    }

    /** Sign-up link, tagged so the free tools can be told apart in analytics. */
    #[Computed]
    public function ctaUrl(): string
    {
        return route('register').'?'.http_build_query([
            'utm_source' => 'free-tool',
            'utm_medium' => 'result',
            'utm_campaign' => $this->run()->tool,
        ]);
    }

    #[Computed]
    public function canRetry(): bool
    {
        return app(ToolRunner::class)->isRetryable($this->run());
    }

    /**
     * Run ONE stage, then ask ourselves for the next. One stage per request is
     * what lets the page report progress truthfully — and what keeps a single
     * request from sitting on two AI calls.
     */
    #[On('tool-advance')]
    public function advance(ToolRunner $runner): void
    {
        $run = $this->run();

        if ($run->status !== ToolRun::STATUS_PROCESSING) {
            return;
        }

        $runner->advance($run);

        $this->forget();

        if ($this->run()->status === ToolRun::STATUS_PROCESSING) {
            $this->dispatch('tool-advance')->self();
        }
    }

    /** Resume from the stage that failed — never from the beginning. */
    public function retry(ToolRunner $runner): void
    {
        if ($runner->retry($this->run())) {
            $this->forget();
            $this->dispatch('tool-advance')->self();
        }
    }

    /** Drop the cached model so every computed property re-reads the run. */
    private function forget(): void
    {
        unset($this->run, $this->steps, $this->report, $this->canRetry, $this->tool, $this->shareUrl, $this->ctaUrl);
    }

    public function render()
    {
        return view('livewire.tools.result')
            ->layout('components.layouts.tool', ['title' => $this->pageTitle()]);
    }

    /**
     * Also the filename the browser suggests when the report is saved as a PDF,
     * which is why it names the job rather than saying "Your result".
     */
    private function pageTitle(): string
    {
        if ($this->expired) {
            return 'Link expired';
        }

        $run = $this->run();
        $job = trim((string) (((array) $run->input_data)['title'] ?? ''));

        if ($run->status === ToolRun::STATUS_COMPLETED && $job !== '') {
            return 'Match report — '.$job;
        }

        return 'Your result';
    }
}
