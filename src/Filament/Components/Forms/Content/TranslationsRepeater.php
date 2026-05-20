<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Components\Forms\Content;

use Capell\Admin\Filament\Components\Forms\ContentEditor;
use Capell\Admin\Filament\Components\Forms\RepeaterTabs;
use Capell\Admin\Filament\Components\Forms\TranslationLanguageSelect;
use Capell\Admin\Filament\Components\Forms\TranslationsRepeater as BaseTranslationsRepeater;
use Capell\Core\Models\Blueprint;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class TranslationsRepeater
{
    public static function make(
        Schema $configurator,
        array $components = [],
        bool $hasTitle = true,
        bool $hasContent = true,
    ): RepeaterTabs {
        $operation = $configurator->getOperation();

        return BaseTranslationsRepeater::make('translations')
            ->when(
                $operation === 'replicate',
                fn (BaseTranslationsRepeater $repeater): BaseTranslationsRepeater => $repeater->withoutRelationship(),
            )
            ->schema([
                ...($hasTitle ? self::getTitleSchema() : []),
                ...($hasContent ? self::getContentSchema($configurator) : []),
                ...$components,
            ]);
    }

    private static function getContentSchema(Schema $configurator): array
    {
        $record = $configurator->getRecord();

        if ($record instanceof Model && $record->relationLoaded('blueprint')) {
            $loadedBlueprint = $record->getRelationValue('blueprint');
            $blueprint = $loadedBlueprint instanceof Blueprint ? $loadedBlueprint : null;
        } else {
            $blueprintId = $configurator->getRawState()['blueprint_id'] ?? null;
            $blueprint = is_numeric($blueprintId) ? Blueprint::query()->find((int) $blueprintId) : null;
        }

        return [
            ContentEditor::make(structure: $blueprint?->content_structure)
                ->requiredBasedOnType(),
        ];
    }

    private static function getTitleSchema(): array
    {
        return [
            Grid::make(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')
                        ->label(__('capell-admin::form.title'))
                        ->columnSpan(fn (Get $get): int => $get('language_id') !== null ? 3 : 2)
                        ->requiredBasedOnType(),
                    TranslationLanguageSelect::make()
                        ->dehydratedWhenHidden()
                        ->hidden(fn (?int $state): bool => (bool) $state),
                ]),
        ];
    }
}
