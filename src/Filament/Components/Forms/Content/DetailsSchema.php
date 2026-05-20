<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Components\Forms\Content;

use Capell\Admin\Filament\Components\Forms\NameInput;
use Filament\Schemas\Schema;

class DetailsSchema
{
    public static function make(Schema $configurator): array
    {
        return [
            NameInput::make('name')
                ->withTitleUpdater(),
            BlueprintSelect::make('blueprint_id')
                ->withRelation()
                ->when(
                    $configurator->isCreating(),
                    fn (BlueprintSelect $component): BlueprintSelect => $component->withCreateForm(),
                    fn (BlueprintSelect $component): BlueprintSelect => $component->withEditForm(),
                ),
        ];
    }
}
