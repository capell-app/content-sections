<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Concerns;

use Aimeos\Nestedset\NestedSet;
use Capell\Admin\Facades\CapellAdmin;
use Capell\ContentSections\Models\Section;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Data\AssetData;
use Capell\Core\Enums\BlueprintGroupEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @mixin RelationManager
 */
trait HasAssetsRelationManager
{
    protected static function createResourcesAction(): Action
    {
        return CreateAction::make()
            ->label(__('capell-content-sections::button.add_asset'))
            ->color('primary')
            ->successNotificationTitle(__('capell-content-sections::message.asset_added'))
            ->using(function (array $data, self $livewire): Model {
                throw_if(! isset($data['asset_id']), RuntimeException::class, 'No asset selected');
                throw_if(! $livewire->ownerRecord instanceof Section, RuntimeException::class, 'Owner record is not a section');

                $asset = null;
                $ownerRecord = $livewire->ownerRecord;

                foreach ($data['asset_id'] as $uuid) {
                    $asset = $ownerRecord->assets()->create([
                        'asset_id' => $uuid,
                        'asset_type' => $data['asset_type'],
                        'related_type' => $ownerRecord->getMorphClass(),
                        'related_id' => $ownerRecord->getKey(),
                    ]);
                }

                return $asset;
            });
    }

    protected static function getAssetForm(): array
    {
        return [
            MorphToSelect::make('asset')
                ->types(
                    fn (self $livewire) => CapellCore::getAssets()
                        ->map(fn (AssetData $asset): Type => self::getMorphToSelectType($asset, $livewire->ownerRecord))
                        ->toArray(),
                )
                ->modifyKeySelectUsing(fn (Select $select): Select => $select->multiple()),
        ];
    }

    protected static function getMorphToSelectType(AssetData $asset, Model $record): Type
    {
        return Type::make($asset->model)
            ->titleAttribute($asset->getTitleKey())
            ->modifyOptionsQueryUsing(
                fn (Builder $query): Builder => self::modifyAssetOptionsQuery($query, $asset, $record),
            )
            ->getOptionLabelFromRecordUsing(
                fn (Model $record): string|HtmlString => match ($record::class) {
                    Page::class => self::getPageOptionLabel($record),
                    default => $record->getAttributeValue($asset->getTitleKey()),
                },
            )
            ->modifyKeySelectUsing(
                function (Select $select) use ($asset): Select {
                    $createOptionUsing = $select->getCreateOptionUsing();

                    $adminAsset = CapellAdmin::getAsset($asset->name);

                    return $select->createOptionForm(
                        fn (Schema $configurator): Schema => $adminAsset->formClass::configure(
                            $configurator->operation('createOption')->model($asset->model),
                        ),
                    )
                        ->createOptionUsing(function (Select $component, array $data) use ($asset, $adminAsset, $createOptionUsing): int|string {
                            $page = $adminAsset->createAction !== null
                                ? $adminAsset->createAction::run($data)
                                : $component->evaluate($createOptionUsing);

                            Notification::make()
                                ->title(__('capell-content-sections::message.asset_created_successfully', ['name' => $asset->name]))
                                ->body($page->name)
                                ->send();

                            return $page->getKey();
                        })
                        ->preload()
                        ->searchable();
                },
            );
    }

    protected static function modifyAssetOptionsQuery(Builder $query, AssetData $asset, Model $record): Builder
    {
        return $query
            ->when(
                $record instanceof $asset->model,
                fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()),
            )
            ->whereDoesntHave(
                'assetRelations',
                fn (Builder $query): Builder => self::applyExistingAssetRelationFilter($query, $record),
            )
            ->when(
                $asset->model === Page::class,
                self::applyPageAssetOptionsQuery(...),
            )
            ->when(
                in_array(NestedSet::class, class_uses_recursive($asset->model), true),
                fn (Builder $query): Builder => $query->defaultOrder(),
            );
    }

    protected static function applyExistingAssetRelationFilter(Builder $query, Model $record): Builder
    {
        return $query
            ->where('related_type', $record->getMorphClass())
            ->where('related_id', $record->getKey());
    }

    protected static function applyPageAssetOptionsQuery(Builder $query, bool $isPageAsset = true): Builder
    {
        return $query
            ->with([
                'ancestors',
                'site',
            ])
            ->whereHas('type', self::applySelectablePageTypeQuery(...))
            ->orderBy('site_id');
    }

    protected static function applySelectablePageTypeQuery(Builder $query): Builder
    {
        return $query->where(self::applyNonSystemBlueprintGroupQuery(...));
    }

    protected static function applyNonSystemBlueprintGroupQuery(Builder $query): Builder
    {
        return $query
            ->where('group', '!=', BlueprintGroupEnum::System->value)
            ->orWhereNull('group');
    }

    protected static function getPageOptionLabel(Pageable $page): HtmlString
    {
        $label = $page->site->name . ' &raquo; ';

        if ($page instanceof Page) {
            $ancestors = $page->ancestors()->get();

            if ($ancestors->isNotEmpty()) {
                $label .= $ancestors->pluck('name')
                    ->map(fn (string $name): string => Str::limit($name, 30))
                    ->implode(' &raquo; ')
                    . ' &raquo; ';
            }
        }

        return new HtmlString($label . Str::limit($page->name, 40));
    }
}
