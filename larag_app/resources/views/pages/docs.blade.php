<x-app-layout :title="'API Documentation'">

@php
    $base = rtrim(url('/api/v1'), '/');
    $apiRoot = rtrim(url('/api'), '/');
    $s = fn (string $code) => str_replace(['__BASE__', '__APIROOT__'], [$base, $apiRoot], $code);
    $maxMb = rtrim(rtrim(number_format($parsingMaxKb / 1024, 1), '0'), '.');

    $nav = [
        'Getting started' => [
            ['id' => 'introduction',   'label' => 'Introduction'],
            ['id' => 'authentication', 'label' => 'Authentication'],
            ['id' => 'credits',        'label' => 'Credits & billing'],
            ['id' => 'rate-limits',    'label' => 'Rate limits'],
            ['id' => 'errors',         'label' => 'Errors'],
        ],
        'Endpoints' => [
            ['id' => 'matching-score',       'label' => 'Match score',          'method' => 'POST'],
            ['id' => 'parse-resume',         'label' => 'Parse resume',         'method' => 'POST'],
            ['id' => 'matching-score-async', 'label' => 'Match score (async)',  'method' => 'POST'],
            ['id' => 'parse-resume-async',   'label' => 'Parse resume (async)', 'method' => 'POST'],
            ['id' => 'job-status',           'label' => 'Job status',           'method' => 'GET'],
        ],
    ];

    /* ---------------------------------------------------------------
     | Code samples. __BASE__ is replaced with the live API base URL.
     * -------------------------------------------------------------- */

    $authSamples = [
        'curl' => <<<'CODE'
curl __APIROOT__/user \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json"
CODE,
        'php' => <<<'CODE'
<?php
$ch = curl_init('__APIROOT__/user');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer YOUR_API_KEY',
        'Accept: application/json',
    ],
]);
$account = json_decode(curl_exec($ch), true);
curl_close($ch);
CODE,
        'node' => <<<'CODE'
const response = await fetch('__APIROOT__/user', {
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Accept': 'application/json',
  },
});
const account = await response.json();
CODE,
        'python' => <<<'CODE'
import requests

response = requests.get(
    "__APIROOT__/user",
    headers={
        "Authorization": "Bearer YOUR_API_KEY",
        "Accept": "application/json",
    },
)
account = response.json()
CODE,
    ];

    $scoreSamples = [
        'curl' => <<<'CODE'
curl -X POST __BASE__/matching-score \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "job": {
      "title": "Senior PHP Engineer",
      "description": "We are hiring a senior engineer for our SaaS platform...",
      "skills": ["PHP", "Redis"]
    },
    "application": {
      "name": "Jane Doe",
      "resume_text": "8 years building backend APIs...",
      "experience_years": 8,
      "skills": ["PHP", "MySQL"]
    }
  }'
CODE,
        'php' => <<<'CODE'
<?php
$payload = [
    'job' => [
        'title'       => 'Senior PHP Engineer',
        'description' => 'We are hiring a senior engineer for our SaaS platform...',
        'skills'      => ['PHP', 'Redis'],
    ],
    'application' => [
        'name'             => 'Jane Doe',
        'resume_text'      => '8 years building backend APIs...',
        'experience_years' => 8,
        'skills'           => ['PHP', 'MySQL'],
    ],
];

$ch = curl_init('__BASE__/matching-score');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer YOUR_API_KEY',
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);

$result = json_decode(curl_exec($ch), true);
curl_close($ch);

echo $result['score'];   // 87
echo $result['reason'];  // "Strong match: ..."
CODE,
        'node' => <<<'CODE'
const response = await fetch('__BASE__/matching-score', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({
    job: {
      title: 'Senior PHP Engineer',
      description: 'We are hiring a senior engineer for our SaaS platform...',
      skills: ['PHP', 'Redis'],
    },
    application: {
      name: 'Jane Doe',
      resume_text: '8 years building backend APIs...',
      experience_years: 8,
      skills: ['PHP', 'MySQL'],
    },
  }),
});

const result = await response.json();
console.log(result.score, result.reason);
CODE,
        'python' => <<<'CODE'
import requests

payload = {
    "job": {
        "title": "Senior PHP Engineer",
        "description": "We are hiring a senior engineer for our SaaS platform...",
        "skills": ["PHP", "Redis"],
    },
    "application": {
        "name": "Jane Doe",
        "resume_text": "8 years building backend APIs...",
        "experience_years": 8,
        "skills": ["PHP", "MySQL"],
    },
}

response = requests.post(
    "__BASE__/matching-score",
    json=payload,
    headers={
        "Authorization": "Bearer YOUR_API_KEY",
        "Accept": "application/json",
    },
)

result = response.json()
print(result["score"], result["reason"])
CODE,
    ];

    $scoreResponse = <<<'CODE'
{
  "score": 87,
  "reason": "Strong match: 8 years of backend experience covers the core stack; Redis exposure present. Gap: no explicit team-lead experience mentioned.",
  "credits_remaining": 99
}
CODE;

    $parseSamples = [
        'curl' => <<<'CODE'
curl -X POST __BASE__/parse-resume \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json" \
  -F "file=@jane-doe-cv.pdf"
CODE,
        'php' => <<<'CODE'
<?php
$ch = curl_init('__BASE__/parse-resume');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer YOUR_API_KEY',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS     => [
        'file' => new CURLFile('jane-doe-cv.pdf'),
    ],
]);

$result = json_decode(curl_exec($ch), true);
curl_close($ch);

echo $result['data']['fullName'];           // "Jane Doe"
echo $result['meta']['credits_remaining'];  // 97
CODE,
        'node' => <<<'CODE'
import { openAsBlob } from 'node:fs';

const form = new FormData();
form.append('file', await openAsBlob('jane-doe-cv.pdf'), 'jane-doe-cv.pdf');

const response = await fetch('__BASE__/parse-resume', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Accept': 'application/json',
  },
  body: form,
});

const result = await response.json();
console.log(result.data.fullName, result.meta.credits_remaining);
CODE,
        'python' => <<<'CODE'
import requests

with open("jane-doe-cv.pdf", "rb") as f:
    response = requests.post(
        "__BASE__/parse-resume",
        headers={
            "Authorization": "Bearer YOUR_API_KEY",
            "Accept": "application/json",
        },
        files={"file": f},
    )

result = response.json()
print(result["data"]["fullName"], result["meta"]["credits_remaining"])
CODE,
    ];

    $parseResponse = <<<CODE
{
  "data": {
    "fullName": "Jane Doe",
    "email": "jane@doe.dev",
    "phone": "+1 555 010 1234",
    "jobTitle": "Senior PHP Engineer",
    "gender": "",
    "dateOfBirth": "",
    "websiteUrl": "https://janedoe.dev",
    "linkedinUrl": "https://linkedin.com/in/janedoe",
    "summary": "Senior engineer with 8 years of backend engineering...",
    "experience": [
      {
        "company": "Acme Corp",
        "title": "Senior PHP Engineer",
        "location": "Remote",
        "startDate": "2019-03",
        "endDate": "Present",
        "description": "- Led a major platform migration\\n- Cut API p95 latency by 40%"
      }
    ],
    "education": [
      {
        "institution": "State University",
        "degree": "BSc",
        "field": "Computer Science",
        "location": "Boston, MA",
        "startDate": "2011-09",
        "endDate": "2015-06",
        "grade": "3.8 GPA"
      }
    ],
    "projects": [ ... ],
    "certificates": [ ... ],
    "awards": [ ... ],
    "expertise": [
      { "name": "Backend Development", "level": 5 },
      { "name": "API Design", "level": 4 }
    ],
    "languages": [
      { "name": "English", "level": 5 },
      { "name": "Spanish", "level": 3 }
    ],
    "skills": ["PHP", "Redis", "MySQL", "Docker"],
    "confidence": 0.94
  },
  "meta": {
    "extraction_path": "text",
    "confidence_percent": 94,
    "credits_consumed": {$parsingCosts['text']},
    "credits_remaining": 97
  }
}
CODE;

    $asyncScoreSamples = [
        'curl' => <<<'CODE'
curl -X POST __BASE__/matching-score/async \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "job":         { "title": "Senior PHP Engineer", "description": "..." },
    "application": { "resume_text": "..." }
  }'
CODE,
        'php' => <<<'CODE'
<?php
// Same payload as the sync endpoint — only the URL changes.
$ch = curl_init('__BASE__/matching-score/async');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer YOUR_API_KEY',
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);

$job = json_decode(curl_exec($ch), true);
curl_close($ch);

echo $job['job_id'];      // poll this
echo $job['status_url'];  // ...or GET this URL directly
CODE,
        'node' => <<<'CODE'
// Same payload as the sync endpoint — only the URL changes.
const response = await fetch('__BASE__/matching-score/async', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify(payload),
});

const job = await response.json();
console.log(job.job_id, job.status_url);
CODE,
        'python' => <<<'CODE'
# Same payload as the sync endpoint — only the URL changes.
response = requests.post(
    "__BASE__/matching-score/async",
    json=payload,
    headers={
        "Authorization": "Bearer YOUR_API_KEY",
        "Accept": "application/json",
    },
)

job = response.json()
print(job["job_id"], job["status_url"])
CODE,
    ];

    $asyncDispatchResponse = <<<'CODE'
HTTP/1.1 202 Accepted

{
  "job_id": "9c2f6a3e-1d4b-4c8a-9e21-7f3a5b0d8c11",
  "endpoint": "matching-score.v1",
  "status": "queued",
  "queued_at": "2026-07-23T09:15:04+00:00",
  "started_at": null,
  "finished_at": null,
  "result": null,
  "error": null,
  "credits": { "charged": 1, "refunded": 0 },
  "status_url": "__BASE__/jobs/9c2f6a3e-1d4b-4c8a-9e21-7f3a5b0d8c11"
}
CODE;

    $asyncParseSamples = [
        'curl' => <<<'CODE'
curl -X POST __BASE__/parse-resume/async \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json" \
  -F "file=@jane-doe-cv.pdf"
CODE,
        'php' => <<<'CODE'
<?php
$ch = curl_init('__BASE__/parse-resume/async');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer YOUR_API_KEY',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS     => [
        'file' => new CURLFile('jane-doe-cv.pdf'),
    ],
]);

$job = json_decode(curl_exec($ch), true);
curl_close($ch);

echo $job['job_id'];
CODE,
        'node' => <<<'CODE'
import { openAsBlob } from 'node:fs';

const form = new FormData();
form.append('file', await openAsBlob('jane-doe-cv.pdf'), 'jane-doe-cv.pdf');

const response = await fetch('__BASE__/parse-resume/async', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Accept': 'application/json',
  },
  body: form,
});

const job = await response.json();
console.log(job.job_id);
CODE,
        'python' => <<<'CODE'
import requests

with open("jane-doe-cv.pdf", "rb") as f:
    response = requests.post(
        "__BASE__/parse-resume/async",
        headers={
            "Authorization": "Bearer YOUR_API_KEY",
            "Accept": "application/json",
        },
        files={"file": f},
    )

job = response.json()
print(job["job_id"])
CODE,
    ];

    $jobStatusSamples = [
        'curl' => <<<'CODE'
curl __BASE__/jobs/9c2f6a3e-1d4b-4c8a-9e21-7f3a5b0d8c11 \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json"
CODE,
        'php' => <<<'CODE'
<?php
$ch = curl_init('__BASE__/jobs/'.$jobId);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer YOUR_API_KEY',
        'Accept: application/json',
    ],
]);

$job = json_decode(curl_exec($ch), true);
curl_close($ch);

if ($job['status'] === 'completed') {
    print_r($job['result']);
}
CODE,
        'node' => <<<'CODE'
const response = await fetch(`__BASE__/jobs/${jobId}`, {
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Accept': 'application/json',
  },
});

const job = await response.json();

if (job.status === 'completed') {
  console.log(job.result);
}
CODE,
        'python' => <<<'CODE'
import requests

response = requests.get(
    f"__BASE__/jobs/{job_id}",
    headers={
        "Authorization": "Bearer YOUR_API_KEY",
        "Accept": "application/json",
    },
)

job = response.json()

if job["status"] == "completed":
    print(job["result"])
CODE,
    ];

    $jobCompletedResponse = <<<'CODE'
{
  "job_id": "9c2f6a3e-1d4b-4c8a-9e21-7f3a5b0d8c11",
  "endpoint": "matching-score.v1",
  "status": "completed",
  "queued_at": "2026-07-23T09:15:04+00:00",
  "started_at": "2026-07-23T09:15:05+00:00",
  "finished_at": "2026-07-23T09:15:09+00:00",
  "result": {
    "score": 91,
    "reason": "Excellent skills coverage and seniority...",
    "credits_remaining": 98
  },
  "error": null,
  "credits": { "charged": 1, "refunded": 0 },
  "status_url": "__BASE__/jobs/9c2f6a3e-1d4b-4c8a-9e21-7f3a5b0d8c11"
}
CODE;

    $jobFailedResponse = <<<'CODE'
{
  "job_id": "f81a2b90-3c5d-4e6f-8a7b-1c2d3e4f5a6b",
  "endpoint": "resume-parse.v1",
  "status": "failed",
  "result": null,
  "error": {
    "code": "processing_failed",
    "message": "The file could not be read. It may be corrupt or empty."
  },
  "credits": { "charged": 2, "refunded": 2 },
  "status_url": "__BASE__/jobs/f81a2b90-3c5d-4e6f-8a7b-1c2d3e4f5a6b"
}
CODE;

    $errorShape = <<<'CODE'
{
  "error": {
    "code": "insufficient_credits",
    "message": "Insufficient credits remaining on this subscription."
  }
}
CODE;

    $validationErrorShape = <<<'CODE'
HTTP/1.1 422 Unprocessable Entity

{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "errors": {
      "job.title": ["The job.title field is required."]
    }
  }
}
CODE;

    $errors = [
        ['status' => '401', 'code' => 'unauthenticated',         'when' => 'The API key is missing, invalid, or revoked.'],
        ['status' => '402', 'code' => 'no_active_subscription',  'when' => 'The account has no active subscription.'],
        ['status' => '402', 'code' => 'insufficient_credits',    'when' => 'The credit balance cannot cover this call.'],
        ['status' => '403', 'code' => 'job_access_denied',       'when' => 'The job ID belongs to another account.'],
        ['status' => '404', 'code' => 'job_not_found',           'when' => 'No async job exists for that ID.'],
        ['status' => '415', 'code' => 'unsupported_file_type',   'when' => 'The uploaded file type is not supported.'],
        ['status' => '422', 'code' => 'validation_failed',       'when' => 'The request body failed validation (details in errors).'],
        ['status' => '422', 'code' => 'file_processing_failed',  'when' => 'The file was accepted but could not be read (corrupt/empty).'],
        ['status' => '429', 'code' => 'rate_limited',            'when' => 'More than 60 requests in a minute.'],
        ['status' => '502', 'code' => 'ai_provider_error',       'when' => 'The AI engine failed — the credit is refunded automatically.'],
    ];
@endphp

    {{-- Register the shared language store before Alpine boots --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('docsLang', {
                current: localStorage.getItem('docsLang') || 'curl',
                set(lang) {
                    this.current = lang;
                    localStorage.setItem('docsLang', lang);
                },
            });
        });
    </script>

    {{-- Page header --}}
    <section class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold ring-1 ring-indigo-100">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                API Reference — v1
            </span>
            <h1 class="mt-4 text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">API Documentation</h1>
            <p class="mt-3 max-w-2xl text-slate-600">
                Everything you need to integrate AI match scoring and resume parsing:
                authentication, request formats, and copy-paste examples in cURL, PHP, Node.js, and Python.
            </p>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-12">

        {{-- Sidebar --}}
        <aside class="hidden lg:block">
            <nav class="sticky top-24 space-y-8 pb-10" aria-label="Docs navigation">
                @foreach ($nav as $group => $items)
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $group }}</div>
                        <ul class="mt-3 space-y-1 border-l border-slate-200">
                            @foreach ($items as $item)
                                <li>
                                    <a href="#{{ $item['id'] }}" data-docs-link="{{ $item['id'] }}"
                                       class="flex items-center gap-2 -ml-px border-l-2 border-transparent pl-4 py-1 text-sm text-slate-600 hover:text-slate-900 hover:border-slate-300 transition">
                                        <span class="truncate">{{ $item['label'] }}</span>
                                        @isset ($item['method'])
                                            <span @class([
                                                'shrink-0 rounded px-1 py-0.5 text-[10px] font-bold leading-none',
                                                'bg-emerald-50 text-emerald-700' => $item['method'] === 'GET',
                                                'bg-indigo-50 text-indigo-700' => $item['method'] === 'POST',
                                            ])>{{ $item['method'] }}</span>
                                        @endisset
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </aside>

        {{-- Mobile quick-nav --}}
        <details class="lg:hidden mb-8 rounded-xl ring-1 ring-slate-200 bg-white [&_summary::-webkit-details-marker]:hidden">
            <summary class="flex items-center justify-between cursor-pointer px-4 py-3 text-sm font-semibold text-slate-900">
                On this page
                <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <div class="border-t border-slate-200 px-4 py-3 grid grid-cols-2 gap-1">
                @foreach ($nav as $group => $items)
                    @foreach ($items as $item)
                        <a href="#{{ $item['id'] }}" class="py-1.5 text-sm text-slate-600 hover:text-indigo-600">{{ $item['label'] }}</a>
                    @endforeach
                @endforeach
            </div>
        </details>

        {{-- Content --}}
        <div class="min-w-0 space-y-16 pb-20">

            {{-- Introduction --}}
            <section id="introduction" class="scroll-mt-24">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">Introduction</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    The Hiremium API gives your job board or ATS two AI capabilities over plain HTTPS:
                </p>
                <ul class="mt-4 space-y-2 text-sm text-slate-700">
                    <li class="flex gap-2"><span class="text-emerald-500">&#10003;</span><span><strong>Match scoring</strong> — send a job and an application, receive a 0-100 match score with a written reason.</span></li>
                    <li class="flex gap-2"><span class="text-emerald-500">&#10003;</span><span><strong>Resume parsing</strong> — upload a CV file (PDF, DOC, DOCX, TXT, or image) and receive a complete structured candidate profile.</span></li>
                </ul>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    All endpoints accept and return JSON (file uploads use <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">multipart/form-data</code>),
                    are versioned under <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">/v1</code>, and share one API key and one credit pool.
                    Always send an <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">Accept: application/json</code> header.
                </p>
                <div class="mt-6">
                    <x-docs.code-block label="Base URL" :code="$base" />
                </div>
            </section>

            {{-- Authentication --}}
            <section id="authentication" class="scroll-mt-24">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">Authentication</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    Every request is authenticated with a Bearer token. Generate your API key from the
                    <a href="{{ route('app.dashboard') }}" class="font-medium text-indigo-600 hover:text-indigo-800 underline">dashboard</a>
                    (email verification required) and send it in the <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">Authorization</code> header.
                    The key is shown once at creation — store it like a password. Regenerating a key immediately revokes the previous one.
                </p>
                <div class="mt-6">
                    <x-docs.code-tabs label="Verify your key — GET /user" :samples="array_map($s, $authSamples)" />
                </div>
                <div class="mt-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">
                    Keep your key server-side. Never embed it in browser JavaScript or mobile apps — anyone with the key can spend your credits.
                </div>
            </section>

            {{-- Credits --}}
            <section id="credits" class="scroll-mt-24">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">Credits &amp; billing</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    Your subscription includes a credit balance that both features draw from. Each call deducts
                    its price up front; if the AI engine fails, the deduction is refunded automatically and the
                    call is logged as <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">refunded</code>.
                    Successful responses include your remaining balance.
                </p>
                <div class="mt-6 overflow-x-auto rounded-xl ring-1 ring-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Call</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr><td class="px-4 py-2.5 font-mono text-xs">POST /matching-score <span class="text-slate-400">(+ async)</span></td><td class="px-4 py-2.5">1 credit</td></tr>
                            <tr><td class="px-4 py-2.5 font-mono text-xs">POST /parse-resume <span class="text-slate-400">(+ async)</span> — text file</td><td class="px-4 py-2.5">{{ $parsingCosts['text'] }} credits</td></tr>
                            <tr><td class="px-4 py-2.5 font-mono text-xs">POST /parse-resume <span class="text-slate-400">(+ async)</span> — scanned / image</td><td class="px-4 py-2.5">{{ $parsingCosts['vision'] }} credits</td></tr>
                            <tr><td class="px-4 py-2.5 font-mono text-xs">GET /jobs/&lcub;id&rcub;</td><td class="px-4 py-2.5">Free</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-sm text-slate-500">
                    A resume parse costs {{ $parsingCosts['text'] }} credits when text can be extracted directly, and
                    {{ $parsingCosts['vision'] }} credits when the file needs vision processing (scanned PDFs and images).
                    The response's <code class="rounded bg-slate-100 px-1 py-0.5 text-xs font-mono">meta.extraction_path</code>
                    tells you which path was used.
                </p>
            </section>

            {{-- Rate limits --}}
            <section id="rate-limits" class="scroll-mt-24">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">Rate limits</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    The API allows <strong>60 requests per minute</strong> per account. Exceeding it returns
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">429</code> with code
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">rate_limited</code> —
                    back off and retry after a few seconds. For bursts larger than the limit
                    (e.g. scoring a whole applicant pool), use the async endpoints and poll for results.
                </p>
            </section>

            {{-- Errors --}}
            <section id="errors" class="scroll-mt-24">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">Errors</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    Errors use conventional HTTP status codes and always carry a machine-readable
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">error.code</code>
                    plus a human-readable message:
                </p>
                <div class="mt-6 grid gap-4 xl:grid-cols-2">
                    <x-docs.code-block label="Error shape" :code="$errorShape" />
                    <x-docs.code-block label="Validation error (422)" :code="$validationErrorShape" />
                </div>
                <div class="mt-6 overflow-x-auto rounded-xl ring-1 ring-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Status</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Code</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($errors as $error)
                                <tr>
                                    <td class="px-4 py-2.5 font-mono text-xs">{{ $error['status'] }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs">{{ $error['code'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $error['when'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Matching score --}}
            <section id="matching-score" class="scroll-mt-24">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-bold text-white">POST</span>
                    <code class="text-lg font-mono font-semibold text-slate-900">/v1/matching-score</code>
                </div>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Score an application against a job</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    Evaluates how well a candidate fits a vacancy and returns a 0-100 score with a short
                    written reason. The score is computed with fixed weights — required-skills coverage (50),
                    job-relevant experience (25), education (15) and communication &amp; motivation (10) — from
                    findings the model reports about the application, so the same pair always scores the same.
                    Provide the application as text fields, attach a resume file, or both —
                    at minimum, either <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">application.resume_text</code>
                    or <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">resume_file</code> is required.
                    <strong>Costs 1 credit.</strong>
                </p>

                <h3 class="mt-8 text-sm font-semibold uppercase tracking-wider text-slate-400">Request body</h3>
                <div class="mt-3 overflow-x-auto rounded-xl ring-1 ring-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Field</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Type</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ([
                                ['job.title', 'string — required', 'Job title. Max 255 chars.'],
                                ['job.description', 'string — required', 'Full job description. Max 20,000 chars.'],
                                ['job.requirements', 'string — optional', 'Requirements text. Max 10,000 chars.'],
                                ['job.skills[]', 'string[] — optional', 'Required skills, each max 100 chars.'],
                                ['application.name', 'string — optional', 'Candidate name. Max 255 chars.'],
                                ['application.email', 'string — optional', 'Candidate email.'],
                                ['application.resume_text', 'string — required*', 'Resume as plain text, max 30,000 chars. *Required unless resume_file is attached.'],
                                ['application.cover_letter', 'string — optional', 'Cover letter text. Max 10,000 chars.'],
                                ['application.experience_years', 'number — optional', 'Years of experience, 0-80.'],
                                ['application.skills[]', 'string[] — optional', 'Candidate skills, each max 100 chars.'],
                                ['resume_file', 'file — optional', 'CV file: PDF, DOC, DOCX, or TXT, max 3 MB. Send the request as multipart/form-data when attaching it.'],
                            ] as [$field, $type, $desc])
                                <tr>
                                    <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap">{{ $field }}</td>
                                    <td class="px-4 py-2.5 text-xs text-slate-500 whitespace-nowrap">{{ $type }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $desc }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 space-y-4">
                    <x-docs.code-tabs label="POST /v1/matching-score" :samples="array_map($s, $scoreSamples)" />
                    <x-docs.code-block label="Response — 200 OK" :code="$scoreResponse" />
                </div>
            </section>

            {{-- Parse resume --}}
            <section id="parse-resume" class="scroll-mt-24">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-bold text-white">POST</span>
                    <code class="text-lg font-mono font-semibold text-slate-900">/v1/parse-resume</code>
                </div>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Parse a resume file into structured data</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    Upload a CV and receive a complete structured candidate profile. Text-based files are parsed
                    directly ({{ $parsingCosts['text'] }} credits); scanned PDFs and images go through vision
                    processing ({{ $parsingCosts['vision'] }} credits) — the path is chosen automatically and
                    reported in <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">meta.extraction_path</code>.
                    Uploaded files are deleted right after processing.
                </p>

                <h3 class="mt-8 text-sm font-semibold uppercase tracking-wider text-slate-400">Request body <span class="normal-case font-normal text-slate-400">— multipart/form-data</span></h3>
                <div class="mt-3 overflow-x-auto rounded-xl ring-1 ring-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Field</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Type</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr>
                                <td class="px-4 py-2.5 font-mono text-xs">file</td>
                                <td class="px-4 py-2.5 text-xs text-slate-500 whitespace-nowrap">file — required</td>
                                <td class="px-4 py-2.5 text-slate-600">The resume: PDF, DOC, DOCX, TXT, JPG, PNG, WebP, or GIF. Max {{ $maxMb }} MB.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 space-y-4">
                    <x-docs.code-tabs label="POST /v1/parse-resume" :samples="array_map($s, $parseSamples)" />
                    <x-docs.code-block label="Response — 200 OK (abridged)" :code="$parseResponse" />
                </div>
                <p class="mt-4 text-sm text-slate-500">
                    Every field is always present: missing information comes back as an empty string, empty array, or 0 — nothing is invented.
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-xs font-mono">confidence</code> is the parser's own certainty
                    that it read the document correctly — a decimal from 0 to 1 with two places (also given as a whole percentage in
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-xs font-mono">meta.confidence_percent</code>). Use it the way an ATS does:
                    accept a high-confidence parse automatically and send a low-confidence one for human review.
                    Dates are normalised to <code class="rounded bg-slate-100 px-1 py-0.5 text-xs font-mono">YYYY-MM</code>, ongoing roles use
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-xs font-mono">"Present"</code>, and
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-xs font-mono">expertise</code> /
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-xs font-mono">languages</code> levels run 1-5.
                </p>
            </section>

            {{-- Matching score async --}}
            <section id="matching-score-async" class="scroll-mt-24">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-bold text-white">POST</span>
                    <code class="text-lg font-mono font-semibold text-slate-900">/v1/matching-score/async</code>
                </div>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Score in the background</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    Identical request format to the sync endpoint, but returns immediately with
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">202 Accepted</code> and a job ID
                    to <a href="#job-status" class="font-medium text-indigo-600 hover:text-indigo-800 underline">poll</a>.
                    Ideal for scoring a whole applicant pool when a vacancy closes. The credit is charged at dispatch
                    and refunded automatically if processing ultimately fails.
                </p>
                <div class="mt-6 space-y-4">
                    <x-docs.code-tabs label="POST /v1/matching-score/async" :samples="array_map($s, $asyncScoreSamples)" />
                    <x-docs.code-block label="Response — 202 Accepted" :code="$s($asyncDispatchResponse)" />
                </div>
            </section>

            {{-- Parse resume async --}}
            <section id="parse-resume-async" class="scroll-mt-24">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-bold text-white">POST</span>
                    <code class="text-lg font-mono font-semibold text-slate-900">/v1/parse-resume/async</code>
                </div>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Parse in the background</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    Same upload format as the sync endpoint, returning <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">202 Accepted</code>
                    with a job ID — perfect for bulk-importing an existing CV archive. The cost
                    ({{ $parsingCosts['text'] }} or {{ $parsingCosts['vision'] }} credits, depending on the file)
                    is determined and charged at dispatch.
                </p>
                <div class="mt-6 space-y-4">
                    <x-docs.code-tabs label="POST /v1/parse-resume/async" :samples="array_map($s, $asyncParseSamples)" />
                </div>
            </section>

            {{-- Job status --}}
            <section id="job-status" class="scroll-mt-24">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-bold text-white">GET</span>
                    <code class="text-lg font-mono font-semibold text-slate-900">/v1/jobs/&lcub;job_id&rcub;</code>
                </div>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Poll an async job</h2>
                <p class="mt-3 text-slate-600 leading-relaxed">
                    Returns the current state of an async job:
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">queued</code> →
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">processing</code> →
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">completed</code> or
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">failed</code>.
                    On completion, <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">result</code> holds the same
                    payload the sync endpoint would return. On failure, credits are refunded and
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono">error</code> explains why.
                    Polling is free and works even after your subscription's credits run out —
                    a 2-5 second interval is plenty.
                </p>
                <div class="mt-6 space-y-4">
                    <x-docs.code-tabs label="GET /v1/jobs/{job_id}" :samples="array_map($s, $jobStatusSamples)" />
                    <div class="grid gap-4 xl:grid-cols-2">
                        <x-docs.code-block label="Response — completed" :code="$s($jobCompletedResponse)" />
                        <x-docs.code-block label="Response — failed (refunded)" :code="$s($jobFailedResponse)" />
                    </div>
                </div>
            </section>

            {{-- Bottom CTA --}}
            <section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-8 py-10 text-center text-white shadow-xl">
                <h2 class="text-2xl font-bold tracking-tight">Ready to make your first call?</h2>
                <p class="mt-2 text-indigo-100">Create an account, generate an API key on the dashboard, and paste any example above.</p>
                <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center rounded-md bg-white text-slate-900 hover:bg-slate-100 px-6 py-2.5 text-sm font-semibold w-full sm:w-auto">
                        Create account
                    </a>
                    <a href="{{ route('contact') }}"
                       class="inline-flex items-center justify-center rounded-md bg-indigo-700/40 hover:bg-indigo-700/60 ring-1 ring-white/20 px-6 py-2.5 text-sm font-semibold text-white w-full sm:w-auto">
                        Talk to us
                    </a>
                </div>
            </section>
        </div>
    </div>

    {{-- Sidebar scroll-spy --}}
    <script>
        (function () {
            var links = document.querySelectorAll('[data-docs-link]');
            if (!links.length || !('IntersectionObserver' in window)) return;

            var map = {};
            links.forEach(function (link) { map[link.dataset.docsLink] = link; });

            var activate = function (id) {
                links.forEach(function (link) {
                    var on = link.dataset.docsLink === id;
                    link.classList.toggle('text-indigo-600', on);
                    link.classList.toggle('border-indigo-600', on);
                    link.classList.toggle('font-semibold', on);
                    link.classList.toggle('text-slate-600', !on);
                    link.classList.toggle('border-transparent', !on);
                });
            };

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) activate(entry.target.id);
                });
            }, { rootMargin: '-20% 0px -70% 0px' });

            Object.keys(map).forEach(function (id) {
                var el = document.getElementById(id);
                if (el) observer.observe(el);
            });
        })();
    </script>

</x-app-layout>
