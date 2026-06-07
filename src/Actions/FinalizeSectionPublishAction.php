<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\LayoutBuilder\Actions\RepointWidgetAssetReferencesAction;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

final class FinalizeSectionPublishAction
{
    use AsAction;

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

        RepointWidgetAssetReferencesAction::run($record, $liveSectionId, $record->getKey());

        return $record;
    }
}
