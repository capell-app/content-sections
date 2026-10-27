<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\Core\Models\AssetAttachment;
use Capell\Core\Models\Translation;
use Capell\PublishingStudio\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * @method static Model run(Model $source, Workspace $workspace)
 */
final class CloneSectionIntoWorkspaceAction
{
    use AsFake;
    use AsObject;

    public function handle(Model $source, Workspace $workspace): Model
    {
        if (! $source instanceof Section) {
            $clone = $source->replicate();
            $clone->setAttribute('workspace_id', $workspace->id);

            return $clone;
        }

        return DB::transaction(function () use ($source, $workspace): Section {
            $clone = $source->replicate();
            $clone->workspace_id = $workspace->id;
            $clone->shadowed_by_workspace_id = 0;
            $clone->uuid = $source->uuid;
            $clone->save();

            $source->translations()->get()->each(function (Translation $translation) use ($clone): void {
                $translationClone = $translation->replicate();
                $translationClone->translatable_id = $this->sectionKey($clone);
                $translationClone->save();
            });

            $source->assets()->get()->each(function (AssetAttachment $attachment) use ($clone): void {
                $attachmentClone = $attachment->replicate();
                $attachmentClone->related_id = (string) $this->sectionKey($clone);
                $attachmentClone->save();
            });

            // Files are copied, not just rows; media uuids are unique, so the draft's copies get new uuids
            // and its meta references follow them.
            CopySectionMediaAction::run($source, $clone);

            return $clone;
        });
    }

    private function sectionKey(Section $section): int
    {
        $key = $section->getKey();

        if (is_int($key)) {
            return $key;
        }

        if (is_string($key) && ctype_digit($key)) {
            return (int) $key;
        }

        throw new RuntimeException('Section key must be an integer.');
    }
}
