# Hiremium | AI-Powered Bulk Resume Screening & Recruitment SaaS

**Live demo:** <https://hiremium.com>

Hiremium is an AI-powered recruitment platform designed to solve one of
the biggest bottlenecks in hiring: **screening hundreds of resumes
against a job's requirements**.

Recruiters can create jobs, bulk-upload CVs (including ZIP archives),
parse candidate information with AI, and rank applicants using match
scores, extracted skills, and job-specific criteria.

The platform is built with Laravel and a queue-driven processing
pipeline. It supports database-backed queues and cron scheduling for
shared-hosting environments, with a path to VPS deployments using Redis
and Laravel Horizon.

------------------------------------------------------------------------

## Screenshots & Walkthrough

### 1. Recruiter Dashboard

![Recruiter dashboard](https://workeera.com/git/hiremium/screenshots/user-dashboard.png)

The main workspace overview, including total candidates, active jobs,
top matches, current billing-cycle AI usage, best candidates, and a
summary of missing skills across the applicant pool.

### 2. Jobs Management

![Jobs management](https://workeera.com/git/hiremium/screenshots/dashboard-jobs.png)

Create and manage job posts, review job status and required skills
(must-have and nice-to-have), and start a resume upload directly from a
job row.

### 3. Matched Candidates & Scoring

![Matched candidates](https://workeera.com/git/hiremium/screenshots/cv-batch-upload-results.png)

Review parsed candidates and their match scores (0--100). Candidates are
grouped into fit categories such as Excellent, Good, and Low Fit based
on the AI analysis and job requirements.

### 4. Candidate Profile & AI Analysis

![Candidate profile](https://workeera.com/git/hiremium/screenshots/resume-match-result-with-reasons.png)

Explore an individual candidate's AI-generated summary, reasons to hire
and potential concerns, Skills/Experience/Education sub-scores, and
matched versus missing skills.

### 5. AI Usage & Token Analytics

![AI usage analytics](https://workeera.com/git/hiremium/screenshots/admin-usage.png)

Workspace admins can monitor AI consumption, including token usage by
provider (OpenAI and Gemini), and review usage trends to help manage
subscription credits.

### 6. Bulk Resume Batches & Queue Processing

![Resume batches](https://workeera.com/git/hiremium/screenshots/admin-resume-batches.png)

Track each uploaded batch through the processing pipeline, including
uploaded, parsed, analyzed, partially completed, and waiting-for-credits
states.

### 7. AI Settings & Guardrails

![AI settings](https://workeera.com/git/hiremium/screenshots/admin-ai-settings.png)

Configure evaluation strictness and job-specific rules. Critical checks
are enforced in application code rather than relying only on AI prompts.
For example, the system calculates experience from CV dates, enforces
must-have skills, and applies score caps when minimum experience
requirements are not met.

### 8. Per-File AI Usage Logs

![AI usage logs](https://workeera.com/git/hiremium/screenshots/admin-AI-usage-log.png)

Inspect an audit trail of AI operations, including parsing and matching
actions, model/provider, token consumption, credits deducted, confidence
score, and execution time.

------------------------------------------------------------------------

## Key Features

-   **Bulk resume upload and parsing:** Upload PDF and DOCX resumes in
    bulk, including ZIP archives, with file validation, ZIP extraction,
    and SHA-256-based deduplication.
-   **AI-powered candidate matching:** Evaluate candidates against
    job-specific requirements and return match scores, summaries, and
    skill comparisons.
-   **Hybrid AI processing:** Use standard API requests for batches of
    up to 10 CVs and provider Batch APIs for batches of 11--25 CVs to
    help optimize processing cost.
-   **Application-level evaluation guardrails:** Calculate total
    experience from CV dates, enforce must-have skills, and apply hard
    score caps when defined criteria are not met.
-   **Subscription and credit management:** Support monthly credit
    limits, top-up packs, and a ledger for tracking AI usage and
    deductions.
-   **Queue-driven processing:** Use Laravel's database queue driver and
    scheduler, with resumable processing states:
    `uploaded → parsed → submitted → processing → completed`.
-   **Team and workspace management:** Support multi-tenant workspaces,
    team invitations, and role-based access for Owner, Admin, Recruiter,
    and Viewer roles.
-   **AI usage analytics:** Track token usage, provider-level
    consumption, credits, confidence scores, and execution time.
-   **Shared-hosting compatibility:** Run scheduled queue workers
    through cron without requiring a continuously running worker
    process.
-   **Deployment portability:** Move from database queues on shared
    hosting to Redis and Laravel Horizon on a VPS through configuration
    changes.

------------------------------------------------------------------------

## Tech Stack

  -----------------------------------------------------------------------
  Area                                Technologies
  ----------------------------------- -----------------------------------
  Backend                             Laravel 13, PHP 8.3+

  Admin panel                         Filament 5

  Frontend                            Livewire, Blade, Tailwind CSS,
                                      Alpine.js

  Database                            MySQL / SQLite

  Queues                              Laravel database queue; Redis-ready
                                      deployment

  File storage                        Local storage / S3-compatible
                                      object storage (including
                                      Cloudflare R2)

  AI integrations                     OpenAI (GPT-4o-mini), Google Gemini
                                      (Gemini 1.5 Flash)

  Testing                             Pest PHP / PHPUnit
  -----------------------------------------------------------------------

------------------------------------------------------------------------

## Quick Start & Installation

### Prerequisites

-   PHP 8.3 or later
-   Composer
-   Node.js and NPM
-   MySQL or SQLite
-   OpenAI and/or Google Gemini API credentials for AI functionality

### 1. Clone the repository

``` bash
git clone https://github.com/mohammad-ali238/hiremium.git
cd hiremium
```

### 2. Install PHP dependencies

``` bash
composer install
```

### 3. Configure the environment

``` bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` file with your database credentials and the AI
provider API keys you intend to use.

### 4. Run migrations and seeders

``` bash
php artisan migrate --seed
```

### 5. Install and build frontend assets

``` bash
npm install
npm run build
```

### 6. Start the local development server

``` bash
php artisan serve
```

Visit <http://localhost:8000>.

------------------------------------------------------------------------

## Queue & Scheduler Configuration

Queue processing is required for resume parsing and AI matching.

### Local development

Run the queue worker in a separate terminal:

``` bash
php artisan queue:work
```

Run the scheduler in another terminal:

``` bash
php artisan schedule:work
```

### Production on shared hosting

Configure a cron job to run Laravel's scheduler every minute:

``` cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

The application scheduler is configured to launch short-lived queue
workers using `queue:work --stop-when-empty` with overlap protection.
Make sure the cron command uses the correct absolute project path and
PHP executable for your hosting environment.

------------------------------------------------------------------------

## Running Tests

Run the automated test suite with:

``` bash
php artisan test
```

The project includes 149+ tests covering key application workflows,
including the AI pipeline, credit deductions, and plan limits.

------------------------------------------------------------------------

## Author & Contact

**Ali Malik**

-   **Live project:** [hiremium.com](https://hiremium.com/)
-   **Portfolio / company:** [workeera.com](https://workeera.com/)
-   **LinkedIn:**
    [linkedin.com/in/ali-malik-a8677067](https://www.linkedin.com/in/ali-malik-a8677067)
-   **Email:** <hello@workeera.com> · <malik.mohamad.ali@gmail.com>

------------------------------------------------------------------------

## License

This repository includes an MIT `LICENSE` file. The project description
supplied for this README also states that the project is intended for
portfolio demonstration purposes and that all rights are reserved.
**Please confirm the intended licensing terms before publishing:** the
MIT license and an "all rights reserved" notice communicate different
permissions. This README does not change the repository's existing
`LICENSE` file.
