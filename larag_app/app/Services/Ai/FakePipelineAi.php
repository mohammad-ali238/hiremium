<?php

namespace App\Services\Ai;

use App\Services\Ats\AtsScoreResult;
use App\Services\Experience\ExperienceProfileResult;
use App\Services\Jd\JdResult;
use App\Services\Jd\SeniorityLevels;
use App\Services\ResumeParsing\ParsedResume;
use App\Services\ResumeParsing\ProcessedFile;
use App\Services\Skills\SkillProfileResult;
use App\Support\SiteSettings;

/**
 * Testing-mode stand-in for the real parse + match AI calls in the CV
 * pipeline. Produces deterministic-looking but random results (score 30–95,
 * generated candidate details, canned reasons/skills) so the whole bulk-upload
 * flow — queue, batch lanes, credits, usage logs, ranking — can be exercised
 * without spending a single AI token. Enabled via Site Settings → Testing mode.
 */
class FakePipelineAi
{
    public const PROVIDER = 'fake';

    public const MODEL = 'testing-mode';

    private const FIRST = ['Alex', 'Jordan', 'Sam', 'Taylor', 'Morgan', 'Casey', 'Riley', 'Jamie', 'Avery', 'Quinn', 'Priya', 'Omar', 'Elena', 'Marcus', 'Sofia', 'Liam', 'Aisha', 'Noah', 'Grace', 'Daniel'];

    private const LAST = ['Smith', 'Khan', 'Garcia', 'Chen', 'Okafor', 'Rossi', 'Mueller', 'Patel', 'Silva', 'Nguyen', 'Brown', 'Ivanov', 'Dubois', 'Santos', 'Ahmed', 'Kim', 'Petrova', 'Costa', 'Hall', 'Lee'];

    private const TITLES = ['Software Engineer', 'Senior Developer', 'Project Manager', 'Data Analyst', 'DevOps Engineer', 'UX Designer', 'Marketing Manager', 'Sales Executive', 'QA Engineer', 'Product Manager'];

    private const SKILL_POOL = ['PHP', 'Laravel', 'JavaScript', 'React', 'Python', 'SQL', 'AWS', 'Docker', 'Git', 'REST APIs', 'Communication', 'Leadership', 'Agile', 'Testing', 'CI/CD', 'TypeScript', 'Node.js', 'Kubernetes', 'Data Analysis', 'Project Management'];

    private const STRENGTHS = [
        'Relevant hands-on experience with the core stack',
        'Clear career progression into senior responsibilities',
        'Quantified achievements in previous roles',
        'Strong match on the required technical skills',
        'Recent, directly comparable industry experience',
        'Evidence of mentoring and team leadership',
        'Solid educational background for the role',
    ];

    private const CONCERNS = [
        'Limited exposure to one of the required tools',
        'Short tenure at the most recent position',
        'No explicit mention of a listed nice-to-have skill',
        'Experience is adjacent rather than identical to the role',
        'Seniority level may exceed or fall short of the opening',
        'Gap in employment history worth clarifying',
        'Location or work-mode fit should be confirmed',
    ];

    /** Test hook: force the fake match score (null = random 30–95). */
    public static ?int $forceScore = null;

    public static function enabled(): bool
    {
        return SiteSettings::testingModeEnabled();
    }

    /** Fake parse: plausible candidate details, using the file name to seed a name when it looks like "first-last-...". */
    public static function parse(ProcessedFile $file, string $originalName = ''): ParsedResume
    {
        [$first, $last] = self::nameFrom($originalName);
        // Vary the fake email per file NAME+EXTENSION so 'cv.pdf' and 'cv.docx'
        // don't collapse into one candidate in testing mode. (Byte-identical
        // re-uploads still collapse via file hash — that is real behaviour.)
        $slug = strtolower($first.'.'.$last).'-'.substr(md5(strtolower($originalName)), 0, 4);
        $skills = self::pick(self::SKILL_POOL, random_int(5, 9));
        $title = self::TITLES[array_rand(self::TITLES)];
        $years = random_int(2, 12);

        $data = ParsedResume::normalize([
            'fullName' => "$first $last",
            'email' => "$slug@example.com",
            'phone' => '+1 555 '.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'jobTitle' => $title,
            'summary' => "[TESTING MODE] {$title} with {$years} years of experience. This profile was generated without calling an AI provider.",
            'skills' => $skills,
            'experience' => [[
                'title' => $title, 'company' => 'Example Corp',
                'startDate' => (string) (date('Y') - $years), 'endDate' => 'Present',
                'description' => 'Fake experience entry produced in testing mode.',
            ]],
            'education' => [['degree' => 'B.Sc.', 'field' => 'Computer Science', 'institution' => 'Example University', 'startDate' => '', 'endDate' => '']],
            // Comfortably above the floor: testing mode must not trip the
            // low-confidence gate and skip the fake match.
            'confidence' => random_int(85, 99) / 100,
        ]);

        return new ParsedResume(
            data: $data,
            tokensUsed: 0,
            provider: self::PROVIDER,
            model: self::MODEL,
            rawResponse: ['testing_mode' => true],
        );
    }

    /**
     * Fake match FINDINGS — the same evidence shape a real provider returns, so
     * MatchRubric does the real arithmetic on top of it in testing mode. The
     * fake "score" is the model's own opinion only (stored as ai_score); the
     * number the recruiter sees is whatever the rubric computes from below.
     */
    public static function score(array $candidateData = [], array $mustHave = [], array $niceToHave = []): AiScoreResult
    {
        $score = self::$forceScore ?? random_int(30, 95);
        $skills = (array) ($candidateData['skills'] ?? self::pick(self::SKILL_POOL, 6));
        shuffle($skills);

        $has = fn (string $sk): bool => in_array(strtolower($sk), array_map('strtolower', $skills), true);
        $verdicts = fn (array $list): array => array_map(fn ($sk) => [
            'skill' => $sk,
            'met' => $has($sk),
            'evidence' => $has($sk) ? '[TESTING MODE] listed in fake skills' : '',
        ], array_values($list));

        // No required skills on the job → the model would infer one or two.
        $required = $mustHave ?: array_slice($skills, 0, min(2, count($skills)));

        $flavour = $score >= 80 ? 'a strong' : ($score >= 60 ? 'a reasonable' : 'a weak');
        $reason = "[TESTING MODE] Fake evaluation: {$flavour} match. "
            .'No AI provider was called.';

        return new AiScoreResult(
            score: $score,
            reason: $reason,
            tokensUsed: 0,
            provider: self::PROVIDER,
            model: self::MODEL,
            rawResponse: ['testing_mode' => true],
            strengths: self::pick(self::STRENGTHS, $score >= 60 ? random_int(3, 4) : random_int(1, 2)),
            concerns: self::pick(self::CONCERNS, $score >= 80 ? random_int(1, 2) : random_int(2, 4)),
            evidence: [
                'skills' => AiScoreResult::normalizeCoverage($verdicts($required)),
                'skills_assumed' => $mustHave === [],
                'relevant_years' => round($score / 10, 1),
                'education_match' => $score >= 50,
                'education_evidence' => $score >= 50 ? '[TESTING MODE] fake related degree' : '',
                'nice_to_have' => AiScoreResult::normalizeCoverage($verdicts($niceToHave)),
                'communication' => max(0, min(100, $score + random_int(-10, 10))),
            ],
        );
    }

    /** Fake ATS score: a plausible full-shape result, no AI provider called. */
    public static function ats(ProcessedFile $file): AtsScoreResult
    {
        $overall = self::$forceScore ?? random_int(35, 92);
        $sub = fn (): int => max(0, min(100, $overall + random_int(-15, 15)));

        $tag = fn (array $texts, string $section): array => array_map(
            fn (string $t): array => ['section' => $section, 'text' => '[TESTING MODE] '.$t],
            $texts,
        );

        return new AtsScoreResult(
            score: $overall,
            band: $overall >= 90 ? 'excellent' : ($overall >= 75 ? 'good' : ($overall >= 60 ? 'fair' : 'weak')),
            subScores: ['ats' => $sub(), 'summary' => $sub(), 'experience' => $sub(), 'skills' => $sub()],
            industry: 'Software',
            specialization: 'Backend Engineering',
            seniority: $overall >= 75 ? 'Senior' : 'Mid',
            targetRoles: ['Backend Developer', 'Software Engineer', 'API Engineer'],
            strengths: $tag(self::pick(self::STRENGTHS, $overall >= 60 ? 3 : 1), 'general'),
            weaknesses: $tag(self::pick(self::CONCERNS, 2), 'general'),
            criticalIssues: $overall < 50 ? $tag(['Missing a professional summary'], 'summary') : [],
            suggestions: $tag(['Quantify achievements with metrics', 'Add a skills section near the top'], 'general'),
            provider: self::PROVIDER,
            model: self::MODEL,
            tokensUsed: 0,
            rawResponse: ['testing_mode' => true, 'file' => $file->filename],
        );
    }

    /**
     * Fake skills profile. Deterministic on purpose: a test asserting the
     * report's sections should not have to cope with random lists.
     */
    public static function skillProfile(string $resumeText = ''): SkillProfileResult
    {
        return SkillProfileResult::normalize([
            'industry' => '[TESTING MODE] Software & IT services',
            'sub_industry' => '[TESTING MODE] B2B SaaS',
            'seniority' => 'Senior',
            'core_skills' => ['PHP', 'Laravel', 'MySQL', 'REST APIs', 'Queue design', 'Testing'],
            'soft_skills' => ['Mentoring', 'Stakeholder communication', 'Ownership'],
            'tools' => ['Git', 'Docker', 'Redis'],
            'about' => '[TESTING MODE] A senior back-end engineer who has spent several years on '
                .'high-traffic PHP applications. Works close to the data layer and has led small '
                .'teams through platform rewrites.',
        ], self::PROVIDER, self::MODEL, 0);
    }

    /** Fake experience profile. Deterministic, like the skills one. */
    public static function experienceProfile(string $resumeText = ''): ExperienceProfileResult
    {
        return ExperienceProfileResult::normalize([
            'industry' => '[TESTING MODE] Software & IT services',
            'sub_industry' => '[TESTING MODE] B2B SaaS',
            'seniority' => 'Senior',
            'about' => '[TESTING MODE] A senior back-end engineer with a decade on high-traffic PHP '
                .'platforms. Has moved from hands-on delivery into leading small teams through '
                .'rewrites, and works closest to the data layer.',
        ], self::PROVIDER, self::MODEL, 0);
    }

    /**
     * Fake job-description analysis. The level issue is tied to the score — a
     * run below 60 reports a mismatch — so a test can drive BOTH branches of the
     * conditional Level Issue section with FakePipelineAi::$forceScore.
     */
    public static function jd(string $title = '', string $seniority = ''): JdResult
    {
        $score = self::$forceScore ?? random_int(35, 92);
        $level = SeniorityLevels::label($seniority);

        $levelIssue = $score < 60
            ? "[TESTING MODE] The responsibilities read below the {$level} level — no ownership or mentoring scope is described."
            : null;

        return new JdResult(
            score: $score,
            band: $score >= 90 ? 'excellent' : ($score >= 75 ? 'good' : ($score >= 60 ? 'fair' : 'weak')),
            // Pay and place are deliberately absent: the analyser is told to
            // ignore them, because the job keeps them in its own fields.
            lacking: [
                '[TESTING MODE] The tech stack is not spelled out',
                '[TESTING MODE] No experience range is given',
                '[TESTING MODE] No team context or reporting line',
            ],
            levelIssue: $levelIssue,
            improvements: [
                '[TESTING MODE] Add 4-6 concrete day-to-day responsibilities',
                '[TESTING MODE] State the experience range and the reporting line',
            ],
            mustHaveSkills: ['PHP', 'Laravel', 'MySQL', 'REST APIs'],
            softSkills: ['Communication', 'Ownership', 'Mentoring'],
            provider: self::PROVIDER,
            model: self::MODEL,
            tokensUsed: 0,
            rawResponse: ['testing_mode' => true, 'title' => $title],
        );
    }

    /** @return array{0:string,1:string} */
    private static function nameFrom(string $filename): array
    {
        $base = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        $parts = array_values(array_filter(preg_split('/[-_\s]+/', $base) ?: []));
        if (count($parts) >= 2 && ctype_alpha($parts[0]) && ctype_alpha($parts[1])) {
            return [ucfirst($parts[0]), ucfirst($parts[1])];
        }

        return [self::FIRST[array_rand(self::FIRST)], self::LAST[array_rand(self::LAST)]];
    }

    private static function pick(array $pool, int $n): array
    {
        $pool = array_values($pool);
        shuffle($pool);

        return array_slice($pool, 0, min($n, count($pool)));
    }
}
