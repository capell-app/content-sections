<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Data\SectionMediaData;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;
use Capell\Core\Models\Media as CoreMedia;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Config;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resolves a media reference stored in section meta to public-safe render data.
 *
 * A reference is a Spatie media uuid. It resolves only when the media is an image attached to this
 * section's block media collection, still exists, and is served from a publicly visible disk with a
 * plain URL. Anything else returns null so callers fall back to text-only output.
 */
final class ResolveSectionMediaAction
{
    use AsFake;
    use AsObject;

    public function handle(Section $section, mixed $reference, ?Language $language = null): ?SectionMediaData
    {
        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $collection = $section->getMedia(Section::BLOCK_MEDIA_COLLECTION);

        // Alt text lives in translations; load them once for the whole collection, not per image.
        new EloquentCollection($collection->filter(static fn (Media $item): bool => $item instanceof CoreMedia)->all())
            ->loadMissing('translations.language');

        $media = $collection->first(static fn (Media $item): bool => $item->uuid === $reference);

        if (! $media instanceof Media || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return null;
        }

        if (Config::get(sprintf('filesystems.disks.%s.visibility', $media->disk)) !== 'public') {
            return null;
        }

        $url = $media->getUrl();

        // A signed or tokenised URL would expire and would expose its signature in cached output.
        if ($url === '' || str_contains($url, '?') || str_contains($url, '#')) {
            return null;
        }

        // Core's media metadata owns alt text: a decorative image yields an empty alt, an image without
        // alt text yields null so the caller can fall back to the adjacent visible name.
        return new SectionMediaData(url: $url, alt: $media instanceof CoreMedia ? $this->alt($media, $language) : null);
    }

    /**
     * Alt text in the requested language, falling back to the default language when that language has
     * no translation or no alt text. A decorative flag always yields an empty alt.
     */
    private function alt(CoreMedia $media, ?Language $language): ?string
    {
        $metadata = $media->localizedMetadata($language);

        if ($language instanceof Language && ($metadata->languageId === null || (! $metadata->decorative && trim((string) $metadata->alt) === ''))) {
            $metadata = $media->localizedMetadata();
        }

        if ($metadata->decorative) {
            return '';
        }

        $text = trim((string) $metadata->alt);

        return $text !== '' ? $text : null;
    }
}
