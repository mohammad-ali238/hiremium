<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\ToolDefinition;
use App\Support\ToolSettings;

/**
 * The catalogue of free tools, built from config('tools.tools'). Resolves a
 * URL slug to its definition and answers "is this tool switched on?" (master
 * switch AND the tool's own switch).
 */
class ToolRegistry
{
    /** @var array<string, ToolDefinition>|null */
    private ?array $tools = null;

    /** Every registered tool, enabled or not. @return array<string, ToolDefinition> */
    public function all(): array
    {
        if ($this->tools === null) {
            $this->tools = [];

            foreach ((array) config('tools.tools', []) as $class) {
                if (! class_exists($class)) {
                    continue;
                }

                $tool = app($class);

                if ($tool instanceof ToolDefinition) {
                    $this->tools[$tool->slug()] = $tool;
                }
            }
        }

        return $this->tools;
    }

    /** Only the tools a visitor may use right now. @return array<string, ToolDefinition> */
    public function enabled(): array
    {
        if (! ToolSettings::enabled()) {
            return [];
        }

        return array_filter(
            $this->all(),
            fn (ToolDefinition $tool): bool => ToolSettings::toolEnabled($tool->slug())
        );
    }

    public function find(string $slug): ?ToolDefinition
    {
        return $this->all()[$slug] ?? null;
    }

    /** The tool behind a public URL, or null when it is unknown or switched off. */
    public function findEnabled(string $slug): ?ToolDefinition
    {
        return $this->enabled()[$slug] ?? null;
    }

    public function isEnabled(string $slug): bool
    {
        return $this->findEnabled($slug) !== null;
    }

    /** Slug => name, for admin filters and settings tabs. @return array<string, string> */
    public function options(): array
    {
        return array_map(fn (ToolDefinition $tool): string => $tool->name(), $this->all());
    }
}
