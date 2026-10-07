<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Components\Forms\Content;

use Capell\Admin\Filament\Components\Forms\SiteSelect;
use Capell\ContentSections\Filament\Components\Forms\ContentSelect;
use Capell\ContentSections\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class SettingsSchema
{
    /**
     * @return array<int, Component | Action | ActionGroup | string | Htmlable>
     */
    public static function make(Schema $configurator): array
    {
        return [
            ContentSelect::make('parent_id')
                ->label(__('capell-admin::form.parent'))
                ->lazy()
                ->modifySelectOptionsQueryUsing(function (Builder $query, ?Section $record): void {
                    if ($record instanceof Section) {
                        $query->where('sections.id', '!=', $record->id);
                    }
                })
                ->when(
                    $configurator->isCreating(),
                    fn (ContentSelect $component): Select => $component->withCreateForm(),
                    fn (ContentSelect $component): Select => $component->withEditForm(),
                ),

            SiteSelect::make('site_id')
                ->default(null)
                ->reactive(),
        ];
    }
}
