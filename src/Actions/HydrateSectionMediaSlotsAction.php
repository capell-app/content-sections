<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Replaces media references in section meta with hydrated public render data.
 *
 * Each slot names a list in meta whose items may carry a `media` reference. The reference is removed
 * and, when it resolves, an `image` entry with a URL and alt text takes its place, so public views
 * never query models and public output never carries media identifiers.
 */
final class HydrateSectionMediaSlotsAction
{
    use AsFake;
    use AsObject;

    /** @var array<string, string> Meta list key keyed to the item key holding the media reference. */
    public const array SLOTS = [
        'logos' => 'media',
        'members' => 'media',
    ];

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function handle(Section $section, array $meta, ?Language $language = null): array
    {
        foreach (self::SLOTS as $listKey => $referenceKey) {
            if (! is_array($meta[$listKey] ?? null)) {
                continue;
            }

            foreach ($meta[$listKey] as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $media = ResolveSectionMediaAction::run($section, $item[$referenceKey] ?? null, $language);
                unset($item[$referenceKey], $item['image']);

                if ($media !== null) {
                    $item['image'] = $media->toArray();
                }

                $meta[$listKey][$index] = $item;
            }
        }

        return $meta;
    }
}
