<?php

namespace App\Services\Tools\Contracts;

/**
 * One free tool. Adding a tool to the platform = write this class and list it
 * in config/tools.php; the form, gates, quota, email, result page, admin and
 * cleanup are all shared machinery driven by what is declared here.
 */
interface ToolDefinition
{
    /** URL + storage + settings key, e.g. 'resume-job-match-checker'. */
    public function slug(): string;

    /** Full product name, e.g. 'Resume Job Match Checker'. */
    public function name(): string;

    /** Short label for the header menu. */
    public function menuLabel(): string;

    /** One-line promise shown under the page heading. */
    public function tagline(): string;

    /** The submit button label on the tool form. */
    public function ctaLabel(): string;

    /** Inline SVG path data for the tool's icon (24x24 viewBox, stroke style). */
    public function iconPath(): string;

    /**
     * Form field schema: field key => ['label', 'type', 'rules', 'help', ...].
     * Consumed by the form component and by validation.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array;

    /**
     * File slots: role => ['label', 'required', 'extensions', 'max_mb'].
     *
     * @return array<string, array<string, mixed>>
     */
    public function files(): array;

    /**
     * AI stages this tool can override the provider/model for:
     * stage key => admin label. Order defines the settings fields.
     *
     * @return array<string, string>
     */
    public function aiStages(): array;

    /**
     * Ordered processing stages (class names implementing ToolStage).
     *
     * @return list<class-string<ToolStage>>
     */
    public function stages(): array;

    /** Blade view rendering a completed run's report. */
    public function resultView(): string;

    /** Subject line of the "your result is ready" email. */
    public function emailSubject(): string;

    /**
     * Default SEO meta for the tool page (title, description, keywords).
     *
     * @return array<string, string>
     */
    public function seoDefaults(): array;
}
