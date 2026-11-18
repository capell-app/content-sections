<?php

declare(strict_types=1);

namespace Capell\ContentSections\Bridges;

use Capell\ContentSections\Models\Section;
use Capell\Core\Facades\CapellCore;
use Capell\PublishingStudio\Actions\ComparePublishingRevisionAction;
use Capell\PublishingStudio\Actions\ListPublishingRevisionsAction;
use Capell\PublishingStudio\Actions\RecordPublishingRevisionAction;
use Capell\PublishingStudio\Enums\PublishingRevisionEventEnum;
use Capell\PublishingStudio\Models\PublishingRevision;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Revision history for shared sections, kept in Publishing Studio's revision log so a section
 * shares one history with pages. Publishing Studio stays optional: without it, or before its
 * tables exist, nothing is recorded and no history is offered.
 */
final class PublishingStudioSectionRevisions
{
    private const string PUBLISHING_STUDIO = 'capell-app/publishing-studio';

    public static function available(): bool
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return false;
        }

        if (! CapellCore::isPackageEnabled(self::PUBLISHING_STUDIO)) {
            return false;
        }

        try {
            return Schema::hasTable(new PublishingRevision()->getTable());
        } catch (Throwable) {
            return false;
        }
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

        if (! self::available() || $before === $after) {
            return;
        }

        if ($before !== null && self::revisions($section) === []) {
            self::record($section, PublishingRevisionEventEnum::Published, null, $before, null, (string) __('capell-content-sections::message.revision_baseline_note'));
        }

        self::record($section, PublishingRevisionEventEnum::Published, $before, $after, $actor);
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

        if (! self::available()) {
            return;
        }

        self::record($section, PublishingRevisionEventEnum::Restored, $before, $after, $actor, (string) __('capell-content-sections::message.revision_restored_note', ['version' => $version]));
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

        $history = [];

        foreach (self::revisions($section) as $revision) {
            $history[] = [
                'id' => self::key($revision->getKey()),
                'version' => $revision->version,
                'event' => $revision->event_type->value,
                'created_at' => $revision->created_at?->toIso8601String(),
                'changed' => array_map(strval(...), array_keys(ComparePublishingRevisionAction::run($revision)['changes'])),
            ];
        }

        return $history;
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

        foreach (self::revisions($section) as $revision) {
            if (self::key($revision->getKey()) === $revisionId && is_array($revision->after_payload)) {
                return ['version' => $revision->version, 'snapshot' => $revision->after_payload];
            }
        }

        return null;
    }

    /** @return list<PublishingRevision> */
    private static function revisions(Section $section): array
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return [];
        }

        if (! self::available() || ! $section->exists) {
            return [];
        }

        return array_values(ListPublishingRevisionsAction::run($section)
            ->filter(static fn (PublishingRevision $revision): bool => $revision->revisionable_type === Section::class)
            ->all());
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private static function record(Section $section, PublishingRevisionEventEnum $event, ?array $before, array $after, ?Authenticatable $actor, ?string $notes = null): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        RecordPublishingRevisionAction::run(
            revisionableType: Section::class,
            revisionableId: self::key($section->getKey()),
            revisionableUuid: filled($section->uuid) ? (string) $section->uuid : null,
            eventType: $event,
            beforePayload: $before,
            afterPayload: $after,
            actor: $actor,
            notes: $notes,
        );
    }

    private static function key(mixed $key): int
    {
        return is_numeric($key) ? (int) $key : 0;
    }
}
