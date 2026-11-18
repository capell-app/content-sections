<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\ContentSections\Bridges\PublishingStudioSectionRevisions;
use Capell\ContentSections\Models\Section;
use Capell\Core\Facades\CapellCore;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Revision history for shared sections, kept in Publishing Studio's revision log so a section
 * shares one history with pages. Publishing Studio stays optional: without it, or before its
 * tables exist, nothing is recorded and no history is offered.
 */
final class SectionRevisions
{
    public static function available(): bool
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return false;
        }

        return PublishingStudioSectionRevisions::available();
    }

    /**
     * Records a saved state. The first time a section is recorded its previous state is kept as
     * a baseline revision too, so the version it had before its first tracked edit can be restored.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    public static function recordSaved(Section $section, ?array $before, array $after, ?Authenticatable $actor): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        PublishingStudioSectionRevisions::recordSaved($section, $before, $after, $actor);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    public static function recordRestored(Section $section, ?array $before, array $after, ?Authenticatable $actor, int $version): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        PublishingStudioSectionRevisions::recordRestored($section, $before, $after, $actor, $version);
    }

    /**
     * The section's revisions, newest first, with the fields each one changed.
     *
     * @return list<array{id: int, version: int, event: string, created_at: string|null, changed: list<string>}>
     */
    public static function history(Section $section): array
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return [];
        }

        return PublishingStudioSectionRevisions::history($section);
    }

    /**
     * The snapshot a revision recorded for this section, or null when it is not one of its own.
     *
     * @return array{version: int, snapshot: array<array-key, mixed>}|null
     */
    public static function snapshot(Section $section, int $revisionId): ?array
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return null;
        }

        return PublishingStudioSectionRevisions::snapshot($section, $revisionId);
    }
}
