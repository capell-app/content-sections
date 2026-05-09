<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Resources\Sections\Tables;

use Capell\Admin\Filament\Components\Tables\Columns\IdentifierColumn;
use Capell\Admin\Filament\Components\Tables\Columns\SiteColumn;
use Capell\Admin\Filament\Components\Tables\Columns\TypeColumn;
use Capell\Admin\Filament\Contracts\TableConfigurator;
use Capell\ContentSections\Enums\LayoutTypeEnum;
use Capell\ContentSections\Filament\Components\Tables\Columns\Content\ContentNameColumn;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Site;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SectionSelectionTable implements TableConfigurator
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                /* @var class-string<\Capell\ContentSections\Models\Section> $model */
                $model = Section::class;

                return $model::query()
                    ->with([
                        'site',
                        'translation.language',
                        'translations.language',
                        'type',
                    ]);
            })
            ->defaultSort('updated_at', 'desc')
            ->columns([
                IdentifierColumn::make('id'),
                ContentNameColumn::make('name'),
                TypeColumn::make('type.name'),
                SiteColumn::make('site.name'),
            ])
            ->filters([
                SelectFilter::make('site_id')
                    ->label(__('capell-admin::form.site'))
                    ->options(function (): array {
                        /** @var class-string<Site> $model */
                        $model = Site::class;

                        return $model::query()
                            ->ordered()
                            ->pluck('name', 'id')
                            ->prepend(__('capell-admin::generic.none'), 0)
                            ->toArray();
                    })
                    ->modifyQueryUsing(
                        fn (Builder $query, array $state): Builder => $query->when(
                            $state['value'],
                            fn (Builder $query, int $siteId): Builder => $query->where('site_id', $siteId),
                        )
                            ->when(
                                $state['value'] === 0,
                                fn (Builder $query): Builder => $query->whereNull('site_id'),
                            ),
                    ),
                SelectFilter::make('type_id')
                    ->label(__('capell-admin::form.type'))
                    ->relationship(
                        name: 'type',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where(
                            'type',
                            LayoutTypeEnum::Section->value,
                        )
                            ->enabled(),
                    ),
            ])
            ->modifyQueryUsing(function (Builder $query, HasTable $livewire): Builder {
                $excludeIds = $livewire->getTableArguments()['excludeIds'] ?? [];

                return $query->when(
                    $excludeIds !== [],
                    fn (Builder $query) => $query->whereNotIn('id', $excludeIds),
                );
            });
    }
}
