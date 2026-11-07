<?php

namespace Tests\Feature\ResumeParsing;

use App\Services\ResumeParsing\ParsedResume;
use Tests\TestCase;

class ParsedResumeSchemaTest extends TestCase
{
    public function test_empty_schema_has_all_sections(): void
    {
        $schema = ParsedResume::emptySchema();

        $this->assertSame(
            ['fullName', 'email', 'phone', 'jobTitle', 'gender', 'dateOfBirth', 'websiteUrl', 'linkedinUrl', 'summary', 'experience', 'education', 'projects', 'certificates', 'awards', 'expertise', 'languages', 'skills', 'confidence'],
            array_keys($schema),
        );
    }

    public function test_personal_detail_fields_normalize_as_strings(): void
    {
        $data = ParsedResume::normalize([
            'gender' => 'Female',
            'dateOfBirth' => '1990-04-15',
            'websiteUrl' => 'https://janedoe.dev',
            'linkedinUrl' => 'https://linkedin.com/in/janedoe',
        ]);

        $this->assertSame('Female', $data['gender']);
        $this->assertSame('1990-04-15', $data['dateOfBirth']);
        $this->assertSame('https://janedoe.dev', $data['websiteUrl']);
        $this->assertSame('https://linkedin.com/in/janedoe', $data['linkedinUrl']);

        $empty = ParsedResume::normalize([]);
        $this->assertSame('', $empty['gender']);
        $this->assertSame('', $empty['dateOfBirth']);
        $this->assertSame('', $empty['websiteUrl']);
        $this->assertSame('', $empty['linkedinUrl']);
    }

    public function test_experience_items_include_location(): void
    {
        $data = ParsedResume::normalize([
            'experience' => [
                ['company' => 'ACME', 'title' => 'Dev', 'location' => 'Berlin', 'startDate' => '2020-01', 'endDate' => 'Present', 'description' => 'Work'],
            ],
        ]);

        $this->assertSame('Berlin', $data['experience'][0]['location']);
        $this->assertSame(
            ['company', 'title', 'location', 'startDate', 'endDate', 'description'],
            array_keys($data['experience'][0]),
        );
    }

    public function test_education_items_have_location_month_dates_and_grade(): void
    {
        $data = ParsedResume::normalize([
            'education' => [
                ['institution' => 'MIT', 'degree' => 'BSc', 'field' => 'CS', 'location' => 'Boston', 'startDate' => '2012-09', 'endDate' => '2016-06', 'grade' => 'First Class'],
            ],
        ]);

        $item = $data['education'][0];
        $this->assertSame(
            ['institution', 'degree', 'field', 'location', 'startDate', 'endDate', 'grade'],
            array_keys($item),
        );
        $this->assertSame('2012-09', $item['startDate']);
        $this->assertSame('First Class', $item['grade']);
    }

    public function test_expertise_is_capped_at_five_items(): void
    {
        $items = array_map(
            fn (int $i) => ['name' => "Area {$i}", 'level' => 3],
            range(1, 8),
        );

        $data = ParsedResume::normalize(['expertise' => $items]);

        $this->assertCount(5, $data['expertise']);
    }

    public function test_levels_are_clamped_and_label_variants_mapped(): void
    {
        $data = ParsedResume::normalize([
            'expertise' => [
                ['name' => 'A', 'level' => 7],          // out of range -> 0
                ['name' => 'B', 'level' => 'Level_4'],  // string variant -> 4
                ['name' => 'C', 'level' => 'Expert'],   // label -> 5
            ],
            'languages' => [
                ['name' => 'English', 'level' => 'Native'],       // -> 5
                ['name' => 'German', 'level' => 'Conversational'], // -> 3
                ['name' => 'French', 'level' => 2],
            ],
        ]);

        $this->assertSame(0, $data['expertise'][0]['level']);
        $this->assertSame(4, $data['expertise'][1]['level']);
        $this->assertSame(5, $data['expertise'][2]['level']);
        $this->assertSame(5, $data['languages'][0]['level']);
        $this->assertSame(3, $data['languages'][1]['level']);
        $this->assertSame(2, $data['languages'][2]['level']);
    }

    public function test_certificates_awards_projects_structures(): void
    {
        $data = ParsedResume::normalize([
            'projects' => [
                ['name' => 'API Platform', 'role' => 'Lead', 'url' => 'https://x.test', 'startDate' => '2021-02', 'endDate' => '2022-05', 'description' => 'Built it'],
            ],
            'certificates' => [
                ['name' => 'AWS SA', 'issuer' => 'Amazon', 'issueDate' => '2023-01', 'expiryDate' => '2026-01', 'credentialId' => 'ABC-1', 'url' => 'https://c.test'],
            ],
            'awards' => [
                ['title' => 'Employee of the Year', 'issuer' => 'ACME', 'date' => '2022-12', 'description' => 'Won it'],
            ],
        ]);

        $this->assertSame('Lead', $data['projects'][0]['role']);
        $this->assertSame('ABC-1', $data['certificates'][0]['credentialId']);
        $this->assertSame('2022-12', $data['awards'][0]['date']);
    }

    public function test_skills_remain_flat_string_array(): void
    {
        $data = ParsedResume::normalize([
            'skills' => ['PHP', 'Laravel', '', null, 42],
        ]);

        $this->assertSame(['PHP', 'Laravel', '42'], $data['skills']);
    }

    public function test_garbage_items_and_unknown_keys_are_dropped(): void
    {
        $data = ParsedResume::normalize([
            'certifications' => ['old-key-should-vanish'],
            'languages' => ['English', ['name' => 'German', 'level' => 2]],
            'experience' => 'not-an-array',
        ]);

        $this->assertArrayNotHasKey('certifications', $data);
        $this->assertCount(1, $data['languages'], 'non-object language entries are dropped');
        $this->assertSame('German', $data['languages'][0]['name']);
        $this->assertSame([], $data['experience']);
    }
}
