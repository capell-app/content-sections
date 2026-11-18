<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Translation;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Everything an editor can restore about a shared section, as plain JSON-safe values: its name,
 * meta, visibility window and the title, content and meta of each translation. Pictures and
 * attached assets live in the media library and are not part of the snapshot.
 *
 * @method static array<string, mixed> run(Section $section)
 */
final class BuildSectionRevisionSnapshotAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array<string, mixed>
     */
    public function handle(Section $section): array
    {
        $translations = [];

        foreach ($section->translations()->orderBy('language_id')->get() as $translation) {
            if (! $translation instanceof Translation) {
                continue;
            }

            $translations[] = [
                'language_id' => $translation->language_id,
                'title' => $translation->title,
                'content' => $translation->content,
                'meta' => $translation->meta,
            ];
        }

        return [
            'name' => $section->name,
            'meta' => $section->meta,
            'visible_from' => $section->visible_from?->toIso8601String(),
            'visible_until' => $section->visible_until?->toIso8601String(),
            'translations' => $translations,
        ];
    }
}
