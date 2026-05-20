<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Resources\Sections\Schemas;

use Capell\Admin\Data\Configurators\ConfiguratorContextData;
use Capell\Admin\Filament\Contracts\FormConfigurator;
use Capell\Admin\Support\Configurators\ConfiguratorResolver;
use Capell\ContentSections\Actions\ResolveRequestedSectionBlueprintAction;
use Capell\ContentSections\Enums\ConfiguratorTypeEnum;
use Capell\ContentSections\Filament\Configurators\Sections\DefaultSectionConfigurator;
use Capell\Core\Models\Blueprint;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class SectionForm implements FormConfigurator
{
    public static function configure(Schema $configurator, ?ConfiguratorContextData $context = null): Schema
    {
        $resolver = resolve(ConfiguratorResolver::class);
        $record = $configurator->getRecord();
        $blueprint = null;

        if ($record instanceof Model && $record->relationLoaded('blueprint')) {
            $loadedBlueprint = $record->getRelationValue('blueprint');
            $blueprint = $loadedBlueprint instanceof Blueprint ? $loadedBlueprint : null;
        }

        $blueprintId = $configurator->getRawState()['blueprint_id'] ?? ($record instanceof Model ? $record->getAttribute('blueprint_id') : null);

        if (! $blueprint instanceof Blueprint && $blueprintId !== null) {
            /** @var class-string<Blueprint> $model */
            $model = Blueprint::class;

            $blueprint = $model::query()->find($blueprintId);
        }

        $blueprint ??= ResolveRequestedSectionBlueprintAction::run($configurator->getRawState());

        $adminType = $blueprint instanceof Blueprint
            ? $resolver->resolveForType($blueprint, ConfiguratorTypeEnum::Section, DefaultSectionConfigurator::getKey())
            : DefaultSectionConfigurator::class;

        return $adminType::configure($configurator->columns());
    }
}
