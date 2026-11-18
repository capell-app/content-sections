<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Support\SectionRevisions;
use Capell\Core\Models\Translation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Puts a section back to one of its recorded revisions: name, meta, visibility window and
 * translations. The restore is authorised like an edit and is itself recorded as a revision, so
 * it can be undone the same way. Every page that places the section shows the restored content.
 *
 * @method static Section run(Section $section, int $revisionId)
 */
final class RestoreSectionRevisionAction
{
    use AsFake;
    use AsObject;

    public function handle(Section $section, int $revisionId): Section
    {
        Gate::authorize('update', $section);

        $revision = SectionRevisions::snapshot($section, $revisionId);

        if ($revision === null) {
            throw ValidationException::withMessages([
                'revision' => __('capell-content-sections::message.revision_missing'),
            ]);
        }

        $snapshot = $revision['snapshot'];

        return DB::transaction(function () use ($section, $snapshot, $revision): Section {
            $before = BuildSectionRevisionSnapshotAction::run($section);

            // Revisions recorded by workspace publishing hold the raw row without translations; only the
            // parts a revision actually carries are put back, so nothing it lacks is erased.
            $attributes = [];
            foreach (['name', 'meta', 'visible_from', 'visible_until'] as $field) {
                if (array_key_exists($field, $snapshot)) {
                    $attributes[$field] = $snapshot[$field];
                }
            }

            if (! is_string($attributes['name'] ?? '')) {
                unset($attributes['name']);
            }

            $section->forceFill($attributes)->save();

            if (is_array($snapshot['translations'] ?? null)) {
                $this->restoreTranslations($section, $snapshot['translations']);
            }

            $section->unsetRelation('translations');
            $section->refresh();

            SectionRevisions::recordRestored($section, $before, BuildSectionRevisionSnapshotAction::run($section), auth()->user(), $revision['version']);

            return $section;
        });
    }

    /**
     * @param  array<array-key, mixed>  $translations
     */
    private function restoreTranslations(Section $section, array $translations): void
    {
        $kept = [];

        foreach ($translations as $row) {
            if (! is_array($row) || ! is_numeric($row['language_id'] ?? null)) {
                continue;
            }

            $languageId = (int) $row['language_id'];
            $kept[] = $languageId;

            $translation = $section->translations()->where('language_id', $languageId)->first()
                ?? new Translation(['language_id' => $languageId]);

            $translation->fill([
                'title' => $row['title'] ?? null,
                'content' => $row['content'] ?? null,
                'meta' => $row['meta'] ?? null,
            ]);

            $section->translations()->save($translation);
        }

        foreach ($section->translations()->whereNotIn('language_id', $kept)->get() as $stale) {
            $stale->delete();
        }
    }
}
