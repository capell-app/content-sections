<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions\Fragments;

use Capell\ContentSections\Fragments\SectionFragmentSurrogateKey;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Media;
use Capell\Core\Models\Translation;
use Capell\Frontend\Support\Cache\FragmentCache;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Refreshes cached shared section fragments when media attached to the section changes.
 *
 * A fragment's identity follows the section row and its translation, so replacing, deleting or
 * restoring a section's media, or editing the alt text or decorative flag stored as a translation of
 * that media, would otherwise keep serving the old fragment. Page HTML caching already follows these
 * models through the Html Cache package's model events; this covers the fragment cache.
 */
final class InvalidateSectionFragmentsForMediaAction
{
    use AsFake;
    use AsObject;

    public function handle(Model $changed): bool
    {
        $media = $changed instanceof Translation ? $this->mediaFor($changed) : $changed;

        if (! $media instanceof Media || $media->model_type !== (new Section)->getMorphClass()) {
            return false;
        }

        resolve(FragmentCache::class)->invalidateBySurrogateKey(SectionFragmentSurrogateKey::forId($media->model_id));

        return true;
    }

    private function mediaFor(Translation $translation): ?Media
    {
        if ($translation->translatable_type !== (new Media)->getMorphClass()) {
            return null;
        }

        return Media::query()->withTrashed()->whereKey($translation->translatable_id)->first();
    }
}
