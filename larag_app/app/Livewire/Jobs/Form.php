<?php

namespace App\Livewire\Jobs;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\CandidateJobMatch;
use App\Models\Job;
use App\Services\Activity\ActivityLogger;
use App\Services\Billing\PlanLimits;
use App\Support\RichText;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.dashboard')]
#[Title('Job')]
class Form extends Component
{
    use InteractsWithWorkspace;

    public ?Job $job = null;

    /** Edit page: how many of this job's upload batches are shown (grows by 10 on "Load more"). */
    public int $batchesLimit = 10;

    public function loadMoreBatches(): void
    {
        $this->batchesLimit += 10;
    }

    public string $title = '';

    public ?string $description = '';

    public ?string $employment_type = '';

    public ?string $location = '';

    public string $location_type = 'remote';

    public ?int $min_experience_years = null;

    public ?string $education = '';

    public string $status = 'open';

    public array $mustHave = [];

    public array $niceToHave = [];

    public function mount(?Job $job = null)
    {
        if ($job && $job->exists) {
            Gate::authorize('update', $job);

            $this->job = $job;
            $this->title = $job->title;
            $this->description = $job->description;
            $this->employment_type = $job->employment_type;
            $this->location = $job->location;
            $this->location_type = $job->location_type ?? 'remote';
            $this->min_experience_years = $job->min_experience_years;
            $this->education = $job->education;
            $this->status = $job->status;
            $this->mustHave = $job->must_have_skills ?? [];
            $this->niceToHave = $job->nice_to_have_skills ?? [];

            return;
        }

        Gate::authorize('create', Job::class);

        if (! app(PlanLimits::class)->canAddJob($this->currentWorkspace())) {
            session()->flash('flash_toast', ['type' => 'error', 'message' => "You've reached your plan's job limit. Upgrade to add more."]);

            return $this->redirectRoute('app.jobs', navigate: false);
        }
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            // Rich text: sanitized to a small tag allow-list on save; length
            // limits apply to the VISIBLE text (markup doesn't count).
            'description' => ['nullable', 'string', 'max:60000', function (string $attr, $value, \Closure $fail) {
                $len = RichText::length($value);
                if ($len === 0) {
                    return; // optional field
                }
                if ($len < RichText::MIN_CHARS) {
                    $fail('The description must be at least '.RichText::MIN_CHARS.' characters ('.$len.' so far).');
                }
                if ($len > RichText::MAX_CHARS) {
                    $fail('The description may not exceed '.number_format(RichText::MAX_CHARS).' characters ('.number_format($len).' entered).');
                }
            }],
            'employment_type' => ['nullable', Rule::in(array_keys(Job::employmentTypes()))],
            'location' => ['nullable', 'string', 'max:200'],
            'location_type' => ['required', Rule::in(array_keys(Job::locationTypes()))],
            'min_experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'education' => ['nullable', 'string', 'max:200'],
            'status' => ['required', Rule::in(array_keys(Job::statuses()))],
            'mustHave' => ['array'],
            'mustHave.*' => ['string', 'max:60'],
            'niceToHave' => ['array'],
            'niceToHave.*' => ['string', 'max:60'],
        ];
    }

    /** Save, then go straight to Resume Upload with this job preselected. */
    public function saveAndUpload()
    {
        return $this->save(thenUpload: true);
    }

    public function save(bool $thenUpload = false)
    {
        $data = $this->validate();

        $payload = [
            'title' => $data['title'],
            'description' => RichText::sanitize($data['description'] ?? null),
            'employment_type' => $data['employment_type'] ?: null,
            'location' => $data['location'] ?: null,
            'location_type' => $data['location_type'],
            'min_experience_years' => $data['min_experience_years'],
            'education' => $data['education'] ?: null,
            'status' => $data['status'],
            'must_have_skills' => array_values(array_filter($this->mustHave)),
            'nice_to_have_skills' => array_values(array_filter($this->niceToHave)),
        ];

        if ($this->job) {
            Gate::authorize('update', $this->job);
            $this->job->update($payload);
            ActivityLogger::log('job.updated', $this->job, "Updated job \"{$this->job->title}\"");
            $job = $this->job;
        } else {
            Gate::authorize('create', Job::class);

            if (! app(PlanLimits::class)->canAddJob($this->currentWorkspace())) {
                $this->addError('title', "You've reached your plan's job limit.");

                return null;
            }

            $payload['created_by'] = Auth::id();
            $job = Job::create($payload); // workspace_id auto-stamped by the trait
            ActivityLogger::log('job.created', $job, "Created job \"{$job->title}\"");
        }

        session()->flash('flash_toast', ['type' => 'success', 'message' => 'Job saved.']);

        if ($thenUpload) {
            return $this->redirectRoute('app.upload', ['job' => $job->id], navigate: false);
        }

        return $this->redirectRoute('app.jobs', navigate: false);
    }

    public function render()
    {
        // Edit mode: same per-job stats as the jobs list, plus the job's upload
        // batches (newest first, 10 at a time) for the collapsible "Show uploads".
        $stats = null;
        $batches = collect();
        $batchesTotal = 0;
        if ($this->job) {
            $stats = [
                'matches' => $this->job->matches()->whereNotNull('score')->count(),
                'batches' => $this->job->batches()->count(),
                'shortlisted' => $this->job->matches()->where('status', CandidateJobMatch::STATUS_SHORTLISTED)->count(),
                'rejected' => $this->job->matches()->where('status', CandidateJobMatch::STATUS_REJECTED)->count(),
            ];
            $batchesTotal = $stats['batches'];
            $batches = $this->job->batches()->latest('id')->limit($this->batchesLimit)->get();
        }

        return view('livewire.jobs.form', [
            'employmentTypes' => Job::employmentTypes(),
            'locationTypes' => Job::locationTypes(),
            'statuses' => Job::statuses(),
            'isEdit' => (bool) $this->job,
            'stats' => $stats,
            'batches' => $batches,
            'batchesTotal' => $batchesTotal,
            'batchesHasMore' => $batchesTotal > $this->batchesLimit,
        ]);
    }
}
