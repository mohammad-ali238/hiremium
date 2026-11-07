<?php

namespace App\Services\Ai;

/**
 * Extra JSON-output instruction appended to the scoring system prompt when the
 * recruiter pipeline needs matched/missing skills + rubric sub-scores. Kept
 * separate so the WordPress-facing score() path (which omits it) is unchanged.
 */
class SkillsPrompt
{
    public const INSTRUCTION = <<<'TXT'
In the same JSON object also include:
- "matched_skills": array of the job's required skills the candidate clearly demonstrates.
- "missing_skills": array of the job's required skills the candidate appears to lack.
- "sub_scores": object with integer values 0-100 for "skills", "experience" and "education".
- "strengths": array of 2-5 short phrases — concrete reasons TO hire this candidate for this job (evidence from the application).
- "concerns": array of 2-5 short phrases — concrete reasons NOT to hire or risks/gaps to probe (evidence from the application).
- "must_have_coverage": array with EXACTLY one object per skill listed under "Must-have skills" in the JOB, in the same order, each: {"skill": <the skill name exactly as listed>, "met": true|false, "evidence": <short quote/paraphrase from the application, or "" if not met>}. Judge equivalence semantically: treat standard synonyms, abbreviations, versions and other-language spellings as the same skill (e.g. "k8s" = Kubernetes, "Postgres" = PostgreSQL, "ReactJS" = React), and count a skill as met when it is clearly evidenced by the work history even if not listed in a skills section. If the JOB lists no must-have skills, return [].
Base matched/missing skills only on the job's stated requirements. Keep each strength/concern under 15 words. Return [] for a list only if there is genuinely nothing to say.
TXT;

    /**
     * The COMPLETE system prompt the model receives for recruiter matching:
     * the admin-saved prompt followed by this code-level output contract.
     * Used by the pipeline and by the AI Settings "effective prompt" preview.
     */
    public static function effective(string $savedPrompt): string
    {
        return rtrim($savedPrompt)."\n\n".self::INSTRUCTION;
    }
}
