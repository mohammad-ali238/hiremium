<?php

namespace Tests\Feature\ResumeParsing;

use App\Models\AiSetting;
use App\Models\ToolRun;
use App\Services\ResumeParsing\Exceptions\LowParseConfidenceException;
use App\Services\ResumeParsing\ParseConfidence;
use App\Services\ResumeParsing\ParsedResume;
use App\Services\Tools\Stages\ScoreMatchStage;
use Tests\TestCase;

class ParseConfidenceTest extends TestCase
{
    public function test_normalizes_the_variants_models_actually_return(): void
    {
        $this->assertSame(0.9, ParseConfidence::normalize(0.9));
        $this->assertSame(0.75, ParseConfidence::normalize('0.75'));
        $this->assertSame(0.85, ParseConfidence::normalize('85%'));
        $this->assertSame(0.82, ParseConfidence::normalize(82));
        $this->assertSame(0.68, ParseConfidence::normalize(0.6789));
        $this->assertSame(1.0, ParseConfidence::normalize(150));
        $this->assertSame(0.0, ParseConfidence::normalize(-1));
        $this->assertNull(ParseConfidence::normalize(null));
        $this->assertNull(ParseConfidence::normalize(''));
        $this->assertNull(ParseConfidence::normalize('very sure'));
    }

    public function test_is_always_displayed_as_a_percentage(): void
    {
        $this->assertSame(75, ParseConfidence::percent(0.75));
        $this->assertSame('90%', ParseConfidence::format(0.9));
        $this->assertSame('—', ParseConfidence::format(null));
        $this->assertSame('High', ParseConfidence::label(0.95));
        $this->assertSame('Not reported', ParseConfidence::label(null));
    }

    public function test_the_minimum_decides_whether_a_cv_may_be_scored(): void
    {
        $this->assertTrue(ParseConfidence::isSufficient(0.75));
        $this->assertTrue(ParseConfidence::isSufficient(0.9));
        $this->assertFalse(ParseConfidence::isSufficient(0.74));
        $this->assertFalse(ParseConfidence::isSufficient(0.2));

        // An older parse that never reported a confidence is not blocked.
        $this->assertTrue(ParseConfidence::isSufficient(null));
    }

    public function test_parsed_resume_keeps_the_confidence_as_a_decimal(): void
    {
        $data = ParsedResume::normalize(['fullName' => 'Jane Doe', 'confidence' => '0.8']);

        $this->assertSame(0.8, $data['confidence']);
        $this->assertNull(ParsedResume::normalize([])['confidence']);

        $parsed = new ParsedResume(
            data: $data,
            tokensUsed: 10,
            provider: 'openai',
            model: 'gpt-4o-mini',
            rawResponse: [],
        );

        $this->assertSame(0.8, $parsed->confidence());
    }

    public function test_the_parsing_prompt_asks_for_confidence_only_once(): void
    {
        $this->assertStringContainsString('"confidence"', ParseConfidence::promptAddendum('Parse the resume.'));

        // The shipped default already asks for it, so nothing is appended.
        $this->assertSame('', ParseConfidence::promptAddendum(AiSetting::defaultParsingPrompt()));
    }

    public function test_the_match_stage_refuses_a_resume_it_could_not_read(): void
    {
        $run = new ToolRun([
            'tool' => 'resume-job-match',
            'input_data' => ['title' => 'Senior PHP Engineer'],
            'output' => ['candidate' => ['fullName' => 'Jane Doe', 'confidence' => 0.4]],
        ]);

        $this->expectException(LowParseConfidenceException::class);
        $this->expectExceptionMessageMatches('/40% certainty/');

        (new ScoreMatchStage)->run($run);
    }
}
