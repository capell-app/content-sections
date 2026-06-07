<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\AssetAttachment;
use Capell\Core\Models\Media;
use Capell\Core\Models\Translation;
use Capell\PublishingStudio\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

final class CloneSectionIntoWorkspaceAction
{
    use AsAction;

    public function handle(Model $source, Workspace $workspace): Model
    {
        if (! $source instanceof Section) {
            $clone = $source->replicate();
            $clone->setAttribute('workspace_id', $workspace->id);

            return $clone;
        }

        $clone = $source->replicate();
        $clone->workspace_id = $workspace->id;
        $clone->shadowed_by_workspace_id = 0;
        $clone->uuid = $source->uuid;
        $clone->save();

        $source->translations()->get()->each(function (Translation $translation) use ($clone): void {
            $translationClone = $translation->replicate();
            $translationClone->translatable_id = $clone->getKey();
            $translationClone->save();
        });

        $source->assets()->get()->each(function (AssetAttachment $attachment) use ($clone): void {
            $attachmentClone = $attachment->replicate();
            $attachmentClone->related_id = $clone->getKey();
            $attachmentClone->save();
        });

        $source->media()
            ->where('collection_name', MediaCollectionEnum::Image->value)
            ->get()
            ->each(function (Model $media) use ($clone): void {
                if (! $media instanceof Media) {
                    return;
                }

                $mediaClone = $media->replicate();
                $mediaClone->model_id = $clone->getKey();
                $mediaClone->uuid = (string) Str::uuid();
                $mediaClone->save();
            });

        return $clone;
    }
}
