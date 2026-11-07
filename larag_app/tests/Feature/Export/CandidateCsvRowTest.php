<?php

namespace Tests\Feature\Export;

use App\Models\Candidate;
use App\Models\CandidateJobMatch;
use App\Support\CandidateCsvRow;
use App\Support\SafeCsv;
use Tests\TestCase;

class CandidateCsvRowTest extends TestCase
{
    private function match(array $attrs = [], array $candidate = []): CandidateJobMatch
    {
        $m = new CandidateJobMatch(array_merge([
            'score' => 82,
            'status' => 'new',
            'strengths' => ['Led a team of 4'],
            'concerns' => ['No AWS exposure'],
            'matched_skills' => ['PHP', 'Laravel'],
            'missing_skills' => ['AWS'],
            'score_breakdown' => ['relevant_experience_years' => 4.5],
        ], $attrs));

        $m->setRelation('candidate', new Candidate(array_merge([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+15 344 365 7184',
            'parse_confidence' => 0.95,
        ], $candidate)));

        return $m;
    }

    public function test_headers_replace_category_with_the_new_columns(): void
    {
        $this->assertSame([
            'Candidate', 'Email', 'Phone', 'Job', 'Score',
            'Resume parsing confidence', 'Candidate experience',
            'Reasons to hire', 'Reasons to reject',
            'Status', 'Matched skills', 'Missing skills',
        ], CandidateCsvRow::headers(withJob: true));

        $this->assertNotContains('Category', CandidateCsvRow::headers());
        $this->assertCount(11, CandidateCsvRow::headers());
    }

    public function test_cells_match_the_headers_and_carry_the_new_values(): void
    {
        $cells = CandidateCsvRow::cells($this->match(), withJob: true);

        $this->assertCount(count(CandidateCsvRow::headers(withJob: true)), $cells);
        $this->assertSame('+15 344 365 7184', $cells[2]);
        $this->assertSame('95%', $cells[5]);
        $this->assertSame('4.5', $cells[6]);
        $this->assertSame('Led a team of 4', $cells[7]);
        $this->assertSame('No AWS exposure', $cells[8]);
    }

    public function test_phone_loses_the_formula_guard_apostrophe(): void
    {
        $this->assertSame('+15 344 365 7184', SafeCsv::phone('+15 344 365 7184'));
        $this->assertSame('(044) 123-4567', SafeCsv::phone('(044) 123-4567'));
        $this->assertSame('', SafeCsv::phone(null));
        // Nothing a spreadsheet can resolve into a function call survives.
        $this->assertSame('(1023)', SafeCsv::phone('=HYPERLINK("http://x","1023")'));
    }

    public function test_empty_reasons_say_so(): void
    {
        $cells = CandidateCsvRow::cells($this->match(['strengths' => [], 'concerns' => null]));

        $this->assertSame('No reason to hire', $cells[6]);
        $this->assertSame('No reason to reject', $cells[7]);
    }

    public function test_pre_rubric_rows_leave_experience_and_confidence_blank(): void
    {
        $cells = CandidateCsvRow::cells($this->match(['score_breakdown' => null]));
        $this->assertSame('', $cells[5]);

        $cells = CandidateCsvRow::cells($this->match([], ['parse_confidence' => null]));
        $this->assertSame('', $cells[4]);
    }

    public function test_whole_years_drop_the_decimal(): void
    {
        $cells = CandidateCsvRow::cells($this->match(['score_breakdown' => ['relevant_experience_years' => 3.0]]));
        $this->assertSame('3', $cells[5]);
    }
}
