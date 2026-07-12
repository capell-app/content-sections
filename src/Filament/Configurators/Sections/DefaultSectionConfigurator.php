<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Configurators\Sections;

use Capell\Admin\Contracts\ConfiguratorInterface;
use Capell\Admin\Contracts\ConfiguratorTypeEnumInterface;
use Capell\Admin\Filament\Components\Forms\CallToActionText;
use Capell\Admin\Filament\Components\Forms\FixedWidthSidebar;
use Capell\Admin\Filament\Components\Forms\IconPicker;
use Capell\Admin\Filament\Components\Forms\MediaLibraryFileUpload;
use Capell\Admin\Filament\Components\Forms\PageSelect;
use Capell\Admin\Filament\Components\Forms\PublishSchema;
use Capell\Admin\Filament\Concerns\HasConfigurator;
use Capell\Admin\Filament\Livewire\PublishStatusPanel;
use Capell\ContentSections\Enums\ConfiguratorTypeEnum;
use Capell\ContentSections\Enums\SchemaExtenderEnum;
use Capell\ContentSections\Filament\Components\Forms\Content\DetailsSchema;
use Capell\ContentSections\Filament\Components\Forms\Content\SettingsSchema;
use Capell\ContentSections\Filament\Components\Forms\Content\TranslationsRepeater;
use Capell\ContentSections\Filament\Components\Forms\CustomColorInput;
use Capell\ContentSections\Models\Section as SectionModel;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DefaultSectionConfigurator implements ConfiguratorInterface
{
    use HasConfigurator;

    protected static ConfiguratorTypeEnumInterface $configuratorType = ConfiguratorTypeEnum::Section;

    /**
     * @return iterable<int, mixed>
     */
    public static function getExtenders(): iterable
    {
        return app()->tagged(SchemaExtenderEnum::Section->value);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function make(Schema $configurator): array
    {
        return match ($configurator->getOperation()) {
            'createOption', 'editOption', 'replicate' => $this->getOptionFormSchema($configurator),
            default => $this->getFormSchema($configurator),
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function getMetaSchema(): array
    {
        return [
            IconPicker::make('icon')
                ->label(__('capell-admin::form.icon')),
            MediaLibraryFileUpload::make('image'),
            CustomColorInput::make(
                name: 'color',
                label: __('capell-admin::form.color'),
            ),
            Group::make()
                ->schema([
                    PageSelect::make('page_id')
                        ->label(__('capell-admin::form.related_page')),
                    CallToActionText::make('link_text')
                        ->hiddenJs(<<<'JS'
                             ! $get('page_id')
                        JS),
                ]),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function getOptionFormSchema(Schema $configurator): array
    {
        return [
            ...DetailsSchema::make($configurator),
            TranslationsRepeater::make($configurator)
                ->hiddenLabel()
                ->contained(),
            ...SettingsSchema::make($configurator),
            MediaLibraryFileUpload::make('image'),
            PublishSchema::make($configurator),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function getFormSchema(Schema $configurator): array
    {
        return [
            Section::make()
                ->hiddenOn('edit')
                ->columnSpanFull()
                ->columns()
                ->schema(DetailsSchema::make($configurator))
                ->contained(fn (string $operation): bool => $operation === 'create'),
            FixedWidthSidebar::make()
                ->mainSchema([
                    Tabs::make()
                        ->tabs([
                            $this->translationsTab($configurator),
                            $this->settingsTab($configurator),
                        ]),
                ])
                ->sidebarSchema([
                    ...$this->publishPanel($configurator),
                    Section::make()
                        ->gridContainer()
                        ->columns(['default' => 1, '@lg' => 2])
                        ->schema([
                            ...($configurator->getOperation() !== 'create' ? DetailsSchema::make($configurator) : []),
                            ...SettingsSchema::make($configurator),
                        ]),
                    ...($configurator->getOperation() !== 'edit' ? [PublishSchema::make($configurator)] : []),
                ]),
        ];
    }

    /**
     * The shared WordPress-style publish panel, pinned to the top of the section
     * editor sidebar. Edit only — on create/option there is no record to act on
     * yet, so the slim inline publish-date field covers that case.
     *
     * @return array<int, Livewire>
     */
    protected function publishPanel(Schema $configurator): array
    {
        $record = $configurator->getRecord();

        if ($configurator->getOperation() !== 'edit' || ! $record instanceof SectionModel) {
            return [];
        }

        $key = $record->getKey();

        return [
            Livewire::make(PublishStatusPanel::class, [
                'recordClass' => SectionModel::class,
                'recordId' => is_scalar($key) ? (int) $key : 0,
            ]),
        ];
    }

    protected function settingsTab(Schema $configurator): Tab
    {
        return Tab::make('settings')
            ->label(__('capell-admin::generic.settings'))
            ->statePath('meta')
            ->columns()
            ->schema($this->getMetaSchema());
    }

    protected function translationsTab(Schema $configurator): Tab
    {
        return Tab::make(__('capell-admin::tab.content'))
            ->icon(Heroicon::Language)
            ->schema([
                TranslationsRepeater::make($configurator)
                    ->hiddenLabel(),
            ]);
    }
}
