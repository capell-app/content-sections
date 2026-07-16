<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\LayoutBuilder\Contracts\WidgetAssetReferenceRepointer;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Model run(Model $record)
 */
final class FinalizeSectionPublishAction
{
    use AsFake;
    use AsObject;

    public function handle(Model $record): Model
    {
        if (! $record instanceof Section || blank($record->uuid)) {
            return $record;
        }

        $liveSectionId = Section::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', 0)
            ->where('uuid', $record->uuid)
            ->value('id');

        if ($liveSectionId === null) {
            return $record;
        }

        $draftSectionId = $record->getKey();

        if (
            (! is_int($liveSectionId) && ! is_string($liveSectionId))
            || (! is_int($draftSectionId) && ! is_string($draftSectionId))
        ) {
            return $record;
        }

        app(WidgetAssetReferenceRepointer::class)->repoint($record, $liveSectionId, $draftSectionId);

        return $record;
    }
}
