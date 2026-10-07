<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Components\Forms\Content;

use Capell\Admin\Filament\Components\Forms\NameInput;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class DetailsSchema
{
    /**
     * @return array<int, Component | Action | ActionGroup | string | Htmlable>
     */
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
