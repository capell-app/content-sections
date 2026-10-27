<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Media as CoreMedia;
use Capell\Core\Models\Translation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Copies a section's media, files and alt text included, onto another section.
 *
 * That is the image and video collections plus the block media collection. Section meta refers to
 * block media by uuid and media uuids are unique, so every copy gets a fresh uuid and the target's
 * meta references are remapped to it. The two sections never share a record. An external video has no
 * file, so only its row is copied.
 *
 * An image whose file is missing or unreadable is skipped, so its reference is cleared and renders as
 * text; a zero-byte copy is never kept. If anything unexpected aborts the run, the copies already made
 * are deleted with their files before the exception is rethrown, so no orphans remain.
 */
final class CopySectionMediaAction
{
    use AsFake;
    use AsObject;

    /** @var list<string> */
    private const array COLLECTIONS = [
        MediaCollectionEnum::Image->value,
        MediaCollectionEnum::Video->value,
        Section::BLOCK_MEDIA_COLLECTION,
    ];

    public function handle(Section $source, Section $target): void
    {
        // Replicating a model carries its loaded relations over, so the target would still list the
        // source's media until it is reloaded.
        $target->unsetRelation('media');

        $items = collect(self::COLLECTIONS)
            ->flatMap(static fn (string $collection): array => $source->getMedia($collection)->all());

        if ($items->isEmpty()) {
            return;
        }

        /** @var list<Media> $copies */
        $copies = [];
        $map = [];

        try {
            foreach ($items as $media) {
                $copy = $this->copyFile($media, $target);

                if (! $copy instanceof Media) {
                    continue;
                }

                $copies[] = $copy;
                $this->copyTranslations($media, $copy);

                if (is_string($media->uuid) && is_string($copy->uuid)) {
                    $map[$media->uuid] = $copy->uuid;
                }
            }

            $this->remapMeta($target, $map);
            $target->unsetRelation('media');
        } catch (Throwable $throwable) {
            foreach ($copies as $copy) {
                // Core media is soft deleted; only a force delete removes the files.
                $copy->forceDelete();
            }

            throw $throwable;
        }
    }

    private function copyFile(Media $media, Section $target): ?Media
    {
        if ($media instanceof CoreMedia && $media->isExternalVideo()) {
            $copy = $media->replicate();
            $copy->model_id = $target->id;
            $copy->uuid = (string) Str::uuid();
            $copy->save();

            return $copy;
        }

        if (! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
            return null;
        }

        try {
            $copy = $media->copy($target, $media->collection_name, $media->disk);
        } catch (FileCannotBeAdded|FilesystemException) {
            return null;
        }

        if ($copy->size <= 0 || Storage::disk($copy->disk)->size($copy->getPathRelativeToRoot()) <= 0) {
            $copy->forceDelete();

            return null;
        }

        return $copy;
    }

    /**
     * Core stores alt text, caption and the decorative flag as translations of the media.
     */
    private function copyTranslations(Media $source, Media $copy): void
    {
        if (! $source instanceof CoreMedia) {
            return;
        }

        foreach ($source->translations()->get() as $translation) {
            if (! $translation instanceof Translation) {
                continue;
            }

            $replica = $translation->replicate();
            $replica->translatable_id = $copy->id;
            $replica->save();
        }
    }

    /**
     * @param  array<string, string>  $map
     */
    private function remapMeta(Section $target, array $map): void
    {
        $meta = $target->meta;

        if (! is_array($meta)) {
            return;
        }

        foreach (HydrateSectionMediaSlotsAction::SLOTS as $listKey => $referenceKey) {
            if (! is_array($meta[$listKey] ?? null)) {
                continue;
            }

            $items = [];

            foreach ($meta[$listKey] as $index => $item) {
                if (is_array($item) && is_string($item[$referenceKey] ?? null)) {
                    $item[$referenceKey] = $map[$item[$referenceKey]] ?? null;
                }

                $items[$index] = $item;
            }

            $meta[$listKey] = $items;
        }

        $target->forceFill(['meta' => $meta])->saveQuietly();
    }
}
