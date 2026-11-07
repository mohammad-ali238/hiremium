<?php

namespace Tests\Feature\Matching;

use App\Models\AiSetting;
use App\Models\Job;
use App\Services\Ai\AiScoreResult;
use App\Services\Ai\FakePipelineAi;
use App\Services\Matching\CareerGapGate;
use App\Services\Matching\ExperienceCalculator;
use App\Services\Matching\ExperienceGate;
use App\Services\Matching\MatchRubric;
use App\Services\Matching\SkillsGate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The score is arithmetic now, so it can be asserted exactly.
 */
class MatchRubricTest extends TestCase
{
    use RefreshDatabase;

    /** A findings-only result, as a provider returns it. */
    private function findings(array $evidence, int $aiScore = 50): AiScoreResult
    {
        return new AiScoreResult(
            score: $aiScore,
            reason: 'Fits the role.',
            tokensUsed: 10,
            provider: 'openai',
            model: 'gpt-4o-mini',
            rawResponse: [],
            evidence: array_merge([
                'skills' => [],
                'skills_assumed' => false,
                'relevant_years' => 0.0,
                'education_match' => false,
                'education_evidence' => '',
                'nice_to_have' => [],
                'communication' => 0,
            ], $evidence),
        );
    }

    private function coverage(array $met): array
    {
        $out = [];
        foreach ($met as $skill => $isMet) {
            $out[] = ['skill' => $skill, 'met' => (bool) $isMet, 'evidence' => $isMet ? 'built it at Acme' : ''];
        }

        return $out;
    }

    public function test_quotas_split_on_whether_the_job_lists_nice_to_haves(): void
    {
        $this->assertSame(['skills' => 50, 'experience' => 25, 'education' => 10, 'nice_to_have' => 10, 'communication' => 5], MatchRubric::weights(true));
        $this->assertSame(['skills' => 50, 'experience' => 25, 'education' => 15, 'communication' => 10], MatchRubric::weights(false));

        foreach ([true, false] as $hasNice) {
            $this->assertSame(100, array_sum(MatchRubric::weights($hasNice)));
        }
    }

    public function test_experience_bands(): void
    {
        $this->assertSame(0, MatchRubric::experiencePercent(0));
        $this->assertSame(30, MatchRubric::experiencePercent(0.3));
        $this->assertSame(50, MatchRubric::experiencePercent(0.5));
        $this->assertSame(50, MatchRubric::experiencePercent(2.9));
        $this->assertSame(85, MatchRubric::experiencePercent(3));
        $this->assertSame(85, MatchRubric::experiencePercent(5));
        $this->assertSame(100, MatchRubric::experiencePercent(5.1));
    }

    public function test_full_score_without_nice_to_haves(): void
    {
        $job = Job::fromSnapshot(['id' => 1, 'title' => 'Backend Engineer', 'must_have_skills' => ['PHP', 'Laravel', 'MySQL', 'Redis']]);

        // 2 of 4 required skills = 50% of 50 = 25
        // 4 relevant years = 85% of 25 = 21.25
        // education matched  = 100% of 15 = 15
        // communication 80%  = 80% of 10 = 8
        $scored = MatchRubric::apply($this->findings([
            'skills' => $this->coverage(['PHP' => true, 'Laravel' => true, 'MySQL' => false, 'Redis' => false]),
            'relevant_years' => 4.0,
            'education_match' => true,
            'communication' => 80,
        ], aiScore: 91), $job);

        $this->assertSame(69, $scored->score, '25 + 21.25 + 15 + 8 = 69.25');
        $this->assertSame(91, $scored->aiScore, "the model's own score is kept, not shown");

        $this->assertSame(['PHP', 'Laravel'], $scored->matchedSkills);
        $this->assertSame(['MySQL', 'Redis'], $scored->missingSkills);

        $d = $scored->breakdown['dimensions'];
        $this->assertSame([50, 25, 15, 10], array_column($d, 'quota'));
        $this->assertSame(25.0, $d['skills']['points']);
        $this->assertSame(21.25, $d['experience']['points']);
        $this->assertArrayNotHasKey('nice_to_have', $d);

        // The bars show each dimension's percentage.
        $this->assertSame(['skills' => 50, 'experience' => 85, 'education' => 100, 'communication' => 80], $scored->subScores);
    }

    public function test_nice_to_haves_change_the_split_and_add_a_dimension(): void
    {
        $job = Job::fromSnapshot([
            'id' => 1, 'title' => 'Backend Engineer',
            'must_have_skills' => ['PHP', 'Laravel'],
            'nice_to_have_skills' => ['Docker', 'AWS'],
        ]);

        // skills 100% of 50 = 50; experience 6y = 100% of 25 = 25;
        // education no = 0 of 10; nice-to-have 1 of 2 = 50% of 10 = 5;
        // communication 60% of 5 = 3
        $scored = MatchRubric::apply($this->findings([
            'skills' => $this->coverage(['PHP' => true, 'Laravel' => true]),
            'relevant_years' => 6,
            'education_match' => false,
            'nice_to_have' => $this->coverage(['Docker' => true, 'AWS' => false]),
            'communication' => 60,
        ]), $job);

        $this->assertSame(83, $scored->score, '50 + 25 + 0 + 5 + 3 = 83');
        $this->assertSame(10, $scored->breakdown['dimensions']['nice_to_have']['quota']);
        $this->assertSame(10, $scored->breakdown['dimensions']['education']['quota']);
        $this->assertSame(5, $scored->breakdown['dimensions']['communication']['quota']);
    }

    public function test_a_job_without_nice_to_haves_ignores_any_the_model_returned(): void
    {
        $job = Job::fromSnapshot(['id' => 1, 'title' => 'Dev', 'must_have_skills' => ['PHP']]);

        $scored = MatchRubric::apply($this->findings([
            'skills' => $this->coverage(['PHP' => true]),
            'nice_to_have' => $this->coverage(['Docker' => true]),
        ]), $job);

        $this->assertArrayNotHasKey('nice_to_have', $scored->breakdown['dimensions']);
        $this->assertSame([], $scored->breakdown['nice_to_have_coverage']);
        $this->assertSame(50, $scored->score, 'skills only: 100% of 50');
    }

    public function test_assumed_skills_are_never_treated_as_must_haves(): void
    {
        $job = Job::fromSnapshot(['id' => 1, 'title' => 'Backend Engineer', 'must_have_skills' => []]);

        $scored = MatchRubric::apply($this->findings([
            'skills' => $this->coverage(['Backend development' => false]),
            'skills_assumed' => true,
        ]), $job);

        $this->assertTrue($scored->breakdown['skills_assumed']);
        $this->assertSame([], $scored->mustHaveCoverage, 'skills the model invented are not the job\'s must-haves');
        $this->assertSame(['Backend development'], $scored->missingSkills, 'but they still drive the skills dimension');

        // ...so the must-have cap has nothing to enforce.
        ['capped' => $capped] = SkillsGate::apply($scored, $job, AiSetting::current());
        $this->assertFalse($capped);
    }

    public function test_a_result_without_findings_is_left_alone(): void
    {
        $legacy = new AiScoreResult(
            score: 77, reason: 'Old row.', tokensUsed: 1, provider: 'openai', model: 'gpt-4o-mini', rawResponse: [],
        );

        $scored = MatchRubric::apply($legacy, Job::fromSnapshot(['id' => 1, 'title' => 'Dev']));

        $this->assertSame(77, $scored->score);
        $this->assertSame([], $scored->breakdown);
        $this->assertNull($scored->aiScore);
    }

    public function test_the_api_lane_scores_without_a_job(): void
    {
        $scored = MatchRubric::apply($this->findings([
            'skills' => $this->coverage(['PHP' => true, 'Go' => false]),
            'relevant_years' => 10,
            'education_match' => true,
            'communication' => 50,
        ]));

        // 25 + 25 + 15 + 5 = 70, on the no-nice-to-have split.
        $this->assertSame(70, $scored->score);
        $this->assertArrayNotHasKey('nice_to_have', $scored->breakdown['dimensions']);
    }

    public function test_career_gap_is_read_from_the_cv_dates(): void
    {
        $today = Carbon::parse('2026-10-27');

        $none = ExperienceCalculator::careerGap([
            ['startDate' => '2019-01', 'endDate' => '2022-06'],
            ['startDate' => '2022-08', 'endDate' => 'Present'],
        ], $today);
        $this->assertFalse($none['has_gap'], 'a two-month hop between roles is not a gap');
        $this->assertTrue($none['ongoing']);

        $between = ExperienceCalculator::careerGap([
            ['startDate' => '2019-01', 'endDate' => '2021-03'],
            ['startDate' => '2022-06', 'endDate' => 'Present'],
        ], $today);
        $this->assertTrue($between['has_gap']);
        $this->assertGreaterThan(6, $between['longest_gap_months']);

        $stale = ExperienceCalculator::careerGap([
            ['startDate' => '2019-01', 'endDate' => '2025-01'],
        ], $today);
        $this->assertTrue($stale['has_gap'], 'nothing current and the last role ended long ago');
        $this->assertFalse($stale['ongoing']);

        $this->assertFalse(ExperienceCalculator::careerGap([], $today)['has_gap'], 'no datable roles is not evidence of a gap');
        $this->assertFalse(
            ExperienceCalculator::careerGap([['startDate' => '2019-01', 'endDate' => '']], $today)['has_gap'],
            'an empty end date means the role is current'
        );
    }

    public function test_testing_mode_findings_score_through_the_same_rubric(): void
    {
        $job = Job::fromSnapshot([
            'id' => 1, 'title' => 'Backend Engineer',
            'must_have_skills' => ['PHP', 'Laravel'],
            'nice_to_have_skills' => ['Docker'],
        ]);

        FakePipelineAi::$forceScore = 90;

        $scored = MatchRubric::apply(
            FakePipelineAi::score(['skills' => ['PHP', 'Laravel', 'Docker']], ['PHP', 'Laravel'], ['Docker']),
            $job
        );

        $this->assertSame(90, $scored->aiScore, 'the fake score is the model half only');
        $this->assertGreaterThan(0, $scored->score);
        $this->assertLessThanOrEqual(100, $scored->score);
        $this->assertSame(['PHP', 'Laravel'], $scored->matchedSkills);
        $this->assertCount(2, $scored->mustHaveCoverage);
        $this->assertSame(100, $scored->breakdown['dimensions']['nice_to_have']['percent']);
    }

    public function test_caps_stack_so_the_lowest_one_wins(): void
    {
        $settings = AiSetting::current();
        $settings->forceFill([
            'enforce_min_experience' => true, 'min_experience_cap_score' => 59,
            'enforce_must_have_skills' => true, 'must_have_cap_score' => 74,
            'enforce_career_gap' => true, 'career_gap_cap_score' => 80,
        ]);

        $job = Job::fromSnapshot([
            'id' => 1, 'title' => 'Dev', 'min_experience_years' => 5,
            'must_have_skills' => ['PHP', 'Go'],
        ]);

        $gap = ['has_gap' => true, 'reasons' => ['a 9-month break between roles'], 'longest_gap_months' => 9, 'months_since_last' => 0];

        $scored = MatchRubric::apply($this->findings([
            'skills' => $this->coverage(['PHP' => true, 'Go' => false]),
            'relevant_years' => 2,
            'education_match' => true,
            'communication' => 100,
        ]), $job, $gap);

        // 25 + 12.5 + 15 + 10 = 62.5 -> 63
        $this->assertSame(63, $scored->score);

        // Total experience of 2 years is below the job's 5, so that cap bites
        // first and the other two then have nothing left to lower.
        ['result' => $r] = ExperienceGate::apply($scored, 2.0, $job, $settings);
        $this->assertSame(59, $r->score);
        ['result' => $r, 'capped' => $skillsCapped] = SkillsGate::apply($r, $job, $settings);
        ['result' => $r, 'capped' => $gapCapped] = CareerGapGate::apply($r, $gap, $settings);

        $this->assertSame(59, $r->score, 'the lowest applicable cap is the one that stands');
        $this->assertFalse($skillsCapped);
        $this->assertFalse($gapCapped);
        $this->assertSame(['min_experience'], array_column($r->breakdown['caps'], 'key'));
    }

    public function test_career_gap_cap_lowers_the_score_and_says_why(): void
    {
        $settings = AiSetting::current();
        $settings->forceFill(['enforce_career_gap' => true, 'career_gap_cap_score' => 80]);

        $job = Job::fromSnapshot(['id' => 1, 'title' => 'Dev', 'must_have_skills' => ['PHP']]);
        $scored = MatchRubric::apply($this->findings([
            'skills' => $this->coverage(['PHP' => true]),
            'relevant_years' => 8,
            'education_match' => true,
            'communication' => 100,
        ]), $job, ['has_gap' => true, 'reasons' => ['a 14-month break between roles'], 'longest_gap_months' => 14, 'months_since_last' => 0]);

        $this->assertSame(100, $scored->score);

        ['result' => $capped, 'capped' => $wasCapped] = CareerGapGate::apply($scored, $scored->breakdown['career_gap'], $settings);

        $this->assertTrue($wasCapped);
        $this->assertSame(80, $capped->score);
        $this->assertStringContainsString('Career gap', $capped->concerns[0]);
        $this->assertSame('career_gap', $capped->breakdown['caps'][0]['key']);
        $this->assertSame(100, $capped->breakdown['caps'][0]['score_before']);
        $this->assertSame(50, $capped->breakdown['dimensions']['skills']['quota'], 'capping must not disturb the breakdown');

        // Switched off, or already under the cap: left alone.
        $settings->forceFill(['enforce_career_gap' => false]);
        $this->assertFalse(CareerGapGate::apply($scored, $scored->breakdown['career_gap'], $settings)['capped']);
    }
}
