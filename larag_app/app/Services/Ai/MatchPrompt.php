<?php

namespace App\Services\Ai;

/**
 * The COMPLETE system prompt for matching a candidate against a job — fixed in
 * code, not editable by the admin.
 *
 * The model no longer decides the match score. It reports EVIDENCE (which
 * required skills are backed by work experience, how many job-relevant years
 * the CV shows, whether the education fits, which nice-to-haves appear, how the
 * resume reads on communication/motivation) and App\Services\Matching\MatchRubric
 * turns that evidence into the score with fixed weights. The model's own
 * "score" is still returned — it shapes the wording of "reason" and is kept for
 * the admin — but it never reaches the number a recruiter sees.
 *
 * The weights live in MatchRubric and are deliberately NOT described here: a
 * model that knows the weighting starts optimising for the total instead of
 * reporting what the documents say.
 */
class MatchPrompt
{
    /** The checks, in the order the model must work through them. */
    public const INSTRUCTIONS = <<<'TXT'
You are an expert technical recruiter extracting MATCH EVIDENCE for one candidate against one job.

You do NOT decide the final match score — the hiring system computes it from the findings you report. Report only what the documents actually say, and work through these checks in order.

1) REQUIRED SKILLS
The job's required skills are the ones the JOB lists under "Must-have skills" (or "Required skills" — whichever label it uses).
- Look for each required skill ONLY inside the candidate's work EXPERIENCE items (each item's job title, company and description).
- Set "met": true for a skill ONLY when it is directly related to at least one experience item, and quote that item as the evidence.
- NEVER take a skill from the candidate's own job title, their summary or objective, a skills list, education, certifications, projects, or any other section. A skill that appears only in those places is "met": false.
- Judge equivalence semantically: synonyms, abbreviations, versions and other-language spellings are the same skill ("k8s" = Kubernetes, "Postgres" = PostgreSQL, "ReactJS" = React).
- Return EXACTLY one entry per required skill, in the job's order, spelling each skill exactly as the JOB spells it, and set "skills_assumed": false.
- If the JOB lists NO required skills at all: work out the 1 or 2 (never more) major skills the role clearly demands from the job title and description, judge those by the same experience-only rule, and set "skills_assumed": true.

2) JOB-RELEVANT EXPERIENCE
- Go through the experience items one by one. Count an item ONLY when its role is directly related to this job's title or description.
- Add up the duration of the counted items and report the total in "relevant_experience_years" (a number, one decimal; "Present"/"Current" means today).
- Ignore every unrelated item completely — do not count it, not even partially.
- If nothing is related, report 0.

3) EDUCATION
- Look only at the education section. Set "education_match": true when a qualification there is directly OR indirectly related to this job's title or description, and name it in "education_evidence".
- No education section, or nothing related to this job: false.

4) NICE-TO-HAVE SKILLS
- For each skill listed under "Nice-to-have skills" in the JOB, search the WHOLE resume (any section).
- "met": true only when the skill is actually present or directly related; never assume or infer one.
- Return EXACTLY one entry per nice-to-have skill, in the job's order, spelled exactly as the JOB spells it. If the JOB lists none, return [].

5) COMMUNICATION AND MOTIVATION
- Judge how the resume itself reads: clarity of writing, structure, how concretely achievements are expressed, and any evidence of motivation for this kind of role.
- Report "communication_motivation_percent" as a whole number from 0 to 100 (e.g. 50, 70, 80, 90) — use 0 when the writing is very poor or there is almost nothing to judge.

WRITING THE TEXT FIELDS ("reason", "strengths", "concerns", and every "evidence")
- Base every sentence on the findings above — the skills you marked met/unmet, the items you counted as relevant, the education you named, the nice-to-haves you found.
- Whenever you refer to how much experience the candidate has, cite ONLY the job-relevant years you calculated in check 2. NEVER state the candidate's total or overall years of experience, and never mention experience that you did not count as relevant.
- Do not state any numeric match score, percentage or grade in "reason" — the system computes the score separately and a number here would contradict it.
- No speculation, no flattery, and nothing the documents do not support.
TXT;

    /**
     * The strict JSON contract. $withCandidate adds the parsed-resume object for
     * the batch lane's single combined call.
     */
    public static function jsonContract(bool $withCandidate = false): string
    {
        $candidate = $withCandidate ? <<<'TXT'
- "candidate": {
    "fullName": string, "email": string, "phone": string, "jobTitle": string,
    "summary": string, "skills": string[],
    "experience": [ { "title": string, "company": string, "startDate": string, "endDate": string, "description": string } ],
    "education": [ { "degree": string, "field": string, "institution": string } ],
    "confidence": number between 0 and 1 with at most 2 decimals (e.g. 0.9, 0.8, 0.75) — your own certainty that you read the CV correctly and extracted its data accurately. 0.95-1 = clean machine-readable text with every section clear; 0.75-0.94 = readable with some ambiguity; below 0.75 = scanned, blurred, partly unreadable, or so sparse that key details had to be left empty. Never report high confidence for a document you could not actually read.
  }

TXT : '';

        $lead = $withCandidate
            ? 'You are ALSO extracting the candidate\'s structured details from the APPLICATION text.'."\n"
              .'Return ONE strict JSON object with EXACTLY these top-level keys:'
            : 'Return ONE strict JSON object with EXACTLY these keys:';

        return <<<TXT


{$lead}
{$candidate}- "skills_coverage": [ {"skill": string, "met": boolean, "evidence": string} ]  — check 1, one entry per required skill.
- "skills_assumed": boolean  — true only when the job listed no required skills and you inferred them.
- "relevant_experience_years": number  — check 2.
- "education_match": boolean  — check 3.
- "education_evidence": string  — the qualification you matched, or "".
- "nice_to_have_coverage": [ {"skill": string, "met": boolean, "evidence": string} ]  — check 4, [] when the job lists none.
- "communication_motivation_percent": integer 0-100  — check 5.
- "score": integer 0-100  — your own overall read of the fit. Used only for the wording of "reason" and for internal review; never show it as a number.
- "reason": string (max 80 words)
- "strengths": string[]  (2-5 short reasons TO hire, evidence-based, <15 words each)
- "concerns": string[]   (2-5 short reasons NOT to hire / risks to probe, <15 words each)

Use "" for unknown strings and [] for unknown lists. Return ONLY the JSON object — no markdown, no commentary.
TXT;
    }

    /** What the model receives for a match-only call (live lane, tools, API). */
    public static function system(): string
    {
        return self::INSTRUCTIONS.self::jsonContract(false);
    }

    /** What the model receives for the batch lane's combined parse+match call. */
    public static function combined(): string
    {
        return self::INSTRUCTIONS.self::jsonContract(true);
    }

    /**
     * Normalise the evidence half of a response. Everything the rubric needs to
     * do its arithmetic, with the shapes guaranteed.
     *
     * @return array{skills: array<int, array{skill:string, met:bool, evidence:string}>, skills_assumed: bool, relevant_years: float, education_match: bool, education_evidence: string, nice_to_have: array<int, array{skill:string, met:bool, evidence:string}>, communication: int}
     */
    public static function evidence(array $json): array
    {
        return [
            'skills' => AiScoreResult::normalizeCoverage($json['skills_coverage'] ?? []),
            'skills_assumed' => (bool) ($json['skills_assumed'] ?? false),
            'relevant_years' => max(0.0, round((float) ($json['relevant_experience_years'] ?? 0), 1)),
            'education_match' => filter_var($json['education_match'] ?? false, FILTER_VALIDATE_BOOL),
            'education_evidence' => mb_substr(trim((string) ($json['education_evidence'] ?? '')), 0, 200),
            'nice_to_have' => AiScoreResult::normalizeCoverage($json['nice_to_have_coverage'] ?? []),
            'communication' => max(0, min(100, (int) round((float) ($json['communication_motivation_percent'] ?? 0)))),
        ];
    }
}
