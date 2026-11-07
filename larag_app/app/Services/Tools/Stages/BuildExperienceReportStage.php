<?php

namespace App\Services\Tools\Stages;

use App\Models\ToolRun;
use App\Services\Experience\Duration;
use App\Services\Matching\ExperienceCalculator;
use App\Services\Tools\Contracts\StageResult;

/**
 * Assembles the experience report the result page renders. No AI, no network.
 *
 * The role list and both kinds of total are built HERE rather than asked of the
 * model: they are arithmetic over the dates the parse already produced, so
 * doing them in code makes them consistent between runs and free.
 *
 * Like the skills tool this keeps `candidate` — the full parsed resume is what
 * the "View resume" popup shows — while the raw extracted text still goes, so a
 * shared link carries the structured resume rather than the file verbatim.
 */
class BuildExperienceReportStage extends Stage
{
    public function key(): string
    {
        return 'report';
    }

    public function label(): string
    {
        return 'Building your report';
    }

    public function iconPath(): string
    {
        return 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z';
    }

    public function run(ToolRun $run): StageResult
    {
        $output = (array) $run->output;
        $roles = (array) (((array) ($output['candidate'] ?? []))['experience'] ?? []);

        $output['experience_rows'] = self::rows($roles);

        // Overlapping roles are merged, so two concurrent jobs are not counted
        // twice — the same calculation the recruiter pipeline uses.
        $years = ExperienceCalculator::totalYears($roles);
        $output['total_experience'] = [
            'years' => $years,
            'label' => Duration::humanYears($years),
        ];

        foreach (['resume_text', 'needs_vision', 'file_mime', 'file_size', 'file_name'] as $key) {
            unset($output[$key]);
        }

        $run->output = $output;
        $run->save();

        return StageResult::local([
            'roles' => count($output['experience_rows']),
            'total_experience' => $output['total_experience']['label'],
        ]);
    }

    /**
     * One line per role: designation, company, the dates as the CV wrote them,
     * and how long it lasted. Nothing else — no descriptions, no locations.
     *
     * Newest first, with undated roles kept in the order the CV listed them.
     *
     * @param  array<int, array<string, mixed>>  $roles
     * @return list<array<string, string>>
     */
    private static function rows(array $roles): array
    {
        $rows = [];

        foreach (array_values($roles) as $index => $role) {
            $title = trim((string) ($role['title'] ?? ''));
            $company = trim((string) ($role['company'] ?? ''));

            // A row with neither a designation nor an employer says nothing.
            if ($title === '' && $company === '') {
                continue;
            }

            $start = trim((string) ($role['startDate'] ?? ''));

            $rows[] = [
                'title' => $title ?: $company,
                'company' => $title === '' ? '' : $company,
                'from' => $start,
                'to' => Duration::endLabel($role['endDate'] ?? ''),
                'duration' => Duration::forRole($start, $role['endDate'] ?? ''),
                '_order' => (string) $index,
                '_start' => ExperienceCalculator::parseDate($start, false)?->timestamp,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            // Undated roles sink below dated ones rather than jumping to the top.
            if ($a['_start'] === null || $b['_start'] === null) {
                return ($a['_start'] === null ? 1 : 0) <=> ($b['_start'] === null ? 1 : 0)
                    ?: ((int) $a['_order'] <=> (int) $b['_order']);
            }

            return $b['_start'] <=> $a['_start'];
        });

        return array_values(array_map(
            fn (array $row): array => array_diff_key($row, ['_order' => null, '_start' => null]),
            $rows,
        ));
    }
}
