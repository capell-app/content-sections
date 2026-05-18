<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Resources\Sections\RelationManagers;

use Capell\Admin\Actions\GetAssetResourceUrlAction;
use Capell\Admin\Filament\Components\Tables\Columns\MediaLibraryImageColumn;
use Capell\Admin\Filament\Components\Tables\Columns\NameColumn;
use Capell\Admin\Filament\Concerns\HasRelationManagerBadge;
use Capell\ContentSections\Filament\Concerns\HasAssetsRelationManager;
use Capell\ContentSections\Models\Section;
use Capell\Core\Data\AssetData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\AssetAttachment;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

class SectionAssetsRelationManager extends RelationManager
{
    use HasAssetsRelationManager;
    use HasRelationManagerBadge;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string $relationship = 'assets';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('capell-admin::tab.assets');
    }

    #[Override]
    public function form(Schema $configurator): Schema
    {
        return $configurator->components(static::getAssetForm())->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::applyTableQuery(...))
            ->description(__('capell-admin::generic.content_assets_description'))
            ->columns([
                TextColumn::make('asset_id')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: self::applyAssetIdSearch(...)),
                NameColumn::make('asset.name'),
                MediaLibraryImageColumn::make('asset.image')
                    ->label(__('capell-admin::table.image'))
                    ->collection('image')
                    ->autoEagerLoadRelation(false),
                TextColumn::make('asset_type')
                    ->label(__('capell-admin::table.type'))
                    ->width(0)
                    ->badge(),
            ])
            ->recordUrl(
                fn (AssetAttachment $record): string => GetAssetResourceUrlAction::run($record->asset_type, $record->asset),
            )
            ->filters([
                SelectFilter::make('asset_type')
                    ->label(__('capell-admin::form.asset_type'))
                    ->options(
                        fn (): array => CapellCore::getAssets()
                            ->mapWithKeys(
                                static fn (AssetData $asset): array => [$asset->getKey() => $asset->getLabel()],
                            )
                            ->all(),
                    ),
                SelectFilter::make('blueprint_id')
                    ->label(__('capell-content-sections::form.blueprint'))
                    ->options(fn (): array => Section::getTypes()),
            ])
            ->headerActions([
                self::createResourcesAction(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    protected static function applyAssetIdSearch(Builder $query, string $search): Builder
    {
        return $query->where('asset_id', $search);
    }

    protected static function applyTableQuery(Builder $query): Builder
    {
        return $query->with([
            'asset' => self::applyAssetMorphRelations(...),
        ]);
    }

    protected static function applyAssetMorphRelations(MorphTo $morphTo): void
    {
        $morphTo->morphWith(self::assetMorphRelations());
    }

    protected static function assetMorphRelations(): array
    {
        return CapellCore::getAssets()
            ->mapWithKeys(fn (AssetData $asset): array => [
                $asset->model => method_exists($asset->model, 'getMorphRelations')
                    ? $asset->model::getMorphRelations()
                    : [],
            ])
            ->toArray();
    }
}
