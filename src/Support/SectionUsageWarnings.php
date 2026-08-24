<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\ContentSections\Actions\BuildSectionUsageSummaryAction;
use Capell\ContentSections\Data\SectionUsageSummaryData;
use Capell\ContentSections\Models\Section;
use Illuminate\Support\HtmlString;

/**
 * Formats a fresh {@see BuildSectionUsageSummaryAction} result into the confirmation
 * copy shown before unpublish/delete/force-delete. Always recomputed at the point the
 * modal is rendered (Filament evaluates these closures at mount time), so the copy
 * reflects the exact, current destinations rather than a value cached from the index.
 */
final class SectionUsageWarnings
{
    public static function modalDescription(Section $section, string $consequenceKey): HtmlString
    {
        $usage = BuildSectionUsageSummaryAction::run($section);

        return self::describe($usage, $consequenceKey);
    }

    public static function describe(SectionUsageSummaryData $usage, string $consequenceKey): HtmlString
    {
        if (! $usage->isUsed()) {
            return new HtmlString(e($usage->impactSummary()));
        }

        $lines = array_filter([
            $usage->impactSummary(),
            $usage->destinationsPreview(),
            (string) __($consequenceKey),
        ], fn (string $line): bool => $line !== '');

        $escaped = [];

        foreach ($lines as $line) {
            $escaped[] = e($line);
        }

        return new HtmlString(implode('<br />', $escaped));
    }
}
