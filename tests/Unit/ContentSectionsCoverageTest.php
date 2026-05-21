<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\BuildSectionAssetRenderDataAction;
use Capell\ContentSections\Actions\ModifyContentSelectCreateAction;
use Capell\ContentSections\Enums\ActionLinkEnum;
use Capell\ContentSections\Enums\AssetEnum;
use Capell\ContentSections\Enums\ConfiguratorTypeEnum;
use Capell\ContentSections\Enums\LayoutTypeEnum;
use Capell\ContentSections\Enums\LivewireComponentsEnum;
use Capell\ContentSections\Enums\SectionConfiguratorEnum;
use Capell\ContentSections\Enums\TypeEnum;
use Capell\ContentSections\Filament\Components\Forms\ContentSelect;
use Capell\ContentSections\Filament\Configurators\Blueprints\ContentBlueprintConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\AccordionSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\CallToActionSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\ComparisonSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\CounterSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\DefaultSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\DividerSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\FaqSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\FeaturesSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\HeroSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\LogosSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\PricingSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\StatsSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\TableSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\TabsSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\TeamSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\TestimonialSectionConfigurator;
use Capell\ContentSections\Filament\Configurators\Sections\TimelineSectionConfigurator;
use Capell\ContentSections\Filament\Resources\Sections\SectionResource;
use Capell\ContentSections\Filament\Resources\Sections\Widgets\SectionAlertsWidget;
use Capell\ContentSections\Health\ContentSectionsHealthCheck;
use Capell\ContentSections\Livewire\Assets\Table\SectionAssets;
use Capell\ContentSections\Livewire\Filament\ModalTableSelect;
use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Observers\SectionObserver;
use Capell\ContentSections\Support\DefaultSectionDefinitionProvider;
use Capell\Core\Enums\PublishStatusEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Frontend\Contracts\FrontendComponentRegistryInterface;
use Capell\Frontend\Data\FrontendComponentData;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Livewire;

uses(CreatesAdminUser::class);

it('builds section asset render data from preloaded relations and plain objects', function (): void {
    app()->bind(FrontendComponentRegistryInterface::class, function (): object {
        return new class implements FrontendComponentRegistryInterface
        {
            public function register(string $key, string $component, array $aliases = [], array $props = []): static
            {
                return $this;
            }

            public function resolve(string $component, ?string $default = null): string
            {
                return 'resolved-' . $component;
            }

            public function get(string $key): FrontendComponentData
            {
                return new FrontendComponentData(key: $key, component: 'resolved-' . $key);
            }

            public function has(string $key): bool
            {
                return true;
            }

            public function hasReference(string $component): bool
            {
                return true;
            }

            public function all(): Collection
            {
                return collect();
            }
        };
    });

    $translation = new class
    {
        public string $summary = 'Short summary';

        public string $label = 'Asset title';

        public function getMeta(string $key, mixed $default = null): mixed
        {
            return $key === 'link_text' ? 'Read case study' : $default;
        }
    };

    $asset = new class($translation)
    {
        public array $meta = ['featured' => true];

        public function __construct(private readonly object $translation) {}

        public function relationLoaded(string $relation): bool
        {
            return in_array($relation, ['translation', 'image', 'linkedPage'], true);
        }

        public function getRelation(string $relation): mixed
        {
            return match ($relation) {
                'translation' => $this->translation,
                'image' => (object) ['id' => 10],
                'linkedPage' => new class
                {
                    public function relationLoaded(string $relation): bool
                    {
                        return $relation === 'pageUrl';
                    }

                    public function getRelation(string $relation): object
                    {
                        return (object) ['full_url' => 'https://example.test/page'];
                    }
                },
            };
        }

        public function getMeta(string $key): ?string
        {
            return ['color' => 'primary', 'icon' => 'sparkles'][$key] ?? null;
        }
    };

    $data = BuildSectionAssetRenderDataAction::run($asset, 'card', true, true, true);

    expect($data->componentItem)->toBe('resolved-card')
        ->and($data->image)->toBeObject()
        ->and($data->linkText)->toBe('Read case study')
        ->and($data->summary)->toBe('Short summary')
        ->and($data->title)->toBe('Asset title')
        ->and($data->url)->toBe('https://example.test/page')
        ->and($data->meta)->toBe(['featured' => true])
        ->and($data->color)->toBe('primary')
        ->and($data->icon)->toBe('sparkles');
});

it('exposes default section definitions and enum metadata', function (): void {
    $definitions = collect((new DefaultSectionDefinitionProvider)->definitions());

    expect($definitions)->toHaveCount(17)
        ->and($definitions->pluck('key')->all())->toContain('content', 'hero', 'pricing', 'timeline')
        ->and(ActionLinkEnum::Link->getLabel())->toBeString()
        ->and(ActionLinkEnum::Page->getIcon())->toBe('heroicon-o-document-text')
        ->and(ActionLinkEnum::PublicAction->getIcon())->toBe('heroicon-o-bolt')
        ->and(AssetEnum::Section->getColor())->toBeString()
        ->and(AssetEnum::Section->getLabel())->toBeString()
        ->and(ConfiguratorTypeEnum::Section->getConfigurators())->toContain(SectionConfiguratorEnum::Hero->value)
        ->and(ContentSectionsHealthCheck::compatibleCapellApiVersion())->toBe('^4.0');
});

it('declares section configurator keys for popular section variants', function (object $configurator, string $key): void {
    $reflection = new ReflectionMethod($configurator, 'sectionKey');
    $reflection->setAccessible(true);

    expect($reflection->invoke($configurator))->toBe($key);
})->with([
    'accordion' => fn (): array => [new AccordionSectionConfigurator, 'accordion'],
    'call_to_action' => fn (): array => [new CallToActionSectionConfigurator, 'call_to_action'],
    'comparison' => fn (): array => [new ComparisonSectionConfigurator, 'comparison'],
    'counter' => fn (): array => [new CounterSectionConfigurator, 'counter'],
    'divider' => fn (): array => [new DividerSectionConfigurator, 'divider'],
    'faq' => fn (): array => [new FaqSectionConfigurator, 'faq'],
    'features' => fn (): array => [new FeaturesSectionConfigurator, 'features'],
    'logos' => fn (): array => [new LogosSectionConfigurator, 'logos'],
    'pricing' => fn (): array => [new PricingSectionConfigurator, 'pricing'],
    'stats' => fn (): array => [new StatsSectionConfigurator, 'stats'],
    'table' => fn (): array => [new TableSectionConfigurator, 'table'],
    'tabs' => fn (): array => [new TabsSectionConfigurator, 'tabs'],
    'team' => fn (): array => [new TeamSectionConfigurator, 'team'],
    'timeline' => fn (): array => [new TimelineSectionConfigurator, 'timeline'],
]);

it('exposes default section extenders and testimonial configurator metadata', function (): void {
    expect(DefaultSectionConfigurator::getExtenders())->toBeIterable()
        ->and(new HeroSectionConfigurator)->toBeInstanceOf(DefaultSectionConfigurator::class)
        ->and(new TestimonialSectionConfigurator)->toBeInstanceOf(DefaultSectionConfigurator::class);
});

it('builds the content blueprint configurator admin tab', function (): void {
    $components = (new ContentBlueprintConfigurator)->make(Schema::make()->operation('create'));
    $tabs = collect($components)->first(fn (mixed $component): bool => $component instanceof Tabs);

    expect($components)->not->toBeEmpty()
        ->and($tabs)->toBeInstanceOf(Tabs::class);
});

it('builds popular section meta schemas for all configured section keys', function (object $configurator, array $expectedNames): void {
    $reflection = new ReflectionMethod($configurator, 'metaFields');
    $reflection->setAccessible(true);

    $fields = collect($reflection->invoke($configurator, Schema::make()->operation('edit')));
    $fieldNames = $fields->map(
        fn (mixed $field): ?string => method_exists($field, 'getName') ? $field->getName() : null,
    )->filter()->values()->all();

    expect($fieldNames)->toContain(...$expectedNames);

    $repeater = $fields->first(fn (mixed $field): bool => $field instanceof Repeater);

    if ($repeater instanceof Repeater) {
        expect($repeater->hasItemLabels())->toBeTrue();
    }
})->with([
    'accordion' => fn (): array => [new AccordionSectionConfigurator, ['items', 'first_open']],
    'call_to_action' => fn (): array => [new CallToActionSectionConfigurator, ['image', 'alignment', 'actions']],
    'comparison' => fn (): array => [new ComparisonSectionConfigurator, ['columns', 'rows']],
    'counter' => fn (): array => [new CounterSectionConfigurator, ['counters', 'animate']],
    'divider' => fn (): array => [new DividerSectionConfigurator, ['style', 'spacing']],
    'faq' => fn (): array => [new FaqSectionConfigurator, ['questions', 'first_open']],
    'features' => fn (): array => [new FeaturesSectionConfigurator, ['features', 'columns']],
    'logos' => fn (): array => [new LogosSectionConfigurator, ['logos', 'columns']],
    'pricing' => fn (): array => [new PricingSectionConfigurator, ['plans']],
    'stats' => fn (): array => [new StatsSectionConfigurator, ['stats', 'columns']],
    'table' => fn (): array => [new TableSectionConfigurator, ['caption', 'headers', 'rows']],
    'tabs' => fn (): array => [new TabsSectionConfigurator, ['tabs']],
    'team' => fn (): array => [new TeamSectionConfigurator, ['members', 'columns']],
    'timeline' => fn (): array => [new TimelineSectionConfigurator, ['milestones']],
]);

it('builds testimonial media metadata schema', function (): void {
    $reflection = new ReflectionMethod(TestimonialSectionConfigurator::class, 'getMetaSchema');
    $reflection->setAccessible(true);

    $components = collect($reflection->invoke(new TestimonialSectionConfigurator));

    expect($components)->not->toBeEmpty()
        ->and($components->map(
            fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        )->filter()->values()->all())->toContain('image');
});

it('exposes modal table select query, form, and selection helpers', function (): void {
    $component = new class extends ModalTableSelect
    {
        public function exposeTableQuery(): Builder
        {
            return $this->getTableQuery();
        }

        public function exposeCanSubmitSelectedRecords(): bool
        {
            return $this->canSubmitSelectedRecords();
        }
    };

    $component->tableArguments = ['excludeIds' => [1, 2]];
    $component->tableQuery = fn (): Builder => Section::query();
    $component->isDisabled = false;

    expect($component->getTableArguments())->toBe(['excludeIds' => [1, 2]])
        ->and($component->getSelectRecordsLabel())->toBeString()
        ->and($component->form(Schema::make()))->toBeInstanceOf(Schema::class)
        ->and($component->exposeTableQuery())->toBeInstanceOf(Builder::class)
        ->and($component->selectRecordsAction()->getName())->toBe('selectRecords')
        ->and($component->render()->name())->toBe('capell-content-sections::livewire.filament.blocks-table-select')
        ->and($component->exposeCanSubmitSelectedRecords())->toBeFalse();
});

it('rejects invalid modal table configuration classes', function (): void {
    $this->actingAsAdmin();

    expect(fn (): mixed => Livewire::test(ModalTableSelect::class, [
        'tableConfiguration' => stdClass::class,
        'tableQuery' => Section::query(),
    ]))->toThrow('Table configuration class [stdClass]');
});

it('declares section asset table metadata and filtered queries', function (): void {
    $assetComponent = new class extends SectionAssets
    {
        public function exposeTableQuery(): Builder
        {
            return $this->getTableQuery();
        }
    };
    $assetComponent->existingRecords = [9, 10];

    expect(SectionAssets::getResource())->toBeString()
        ->and($assetComponent->getTableRecordKey(['id' => 42]))->toBe('42')
        ->and($assetComponent->exposeTableQuery())->toBeInstanceOf(Builder::class);
});

it('exposes content select create action and livewire enum metadata', function (): void {
    $select = ModifyContentSelectCreateAction::run(ContentSelect::make('content_id'));

    expect($select)->toBeInstanceOf(Select::class)
        ->and(TypeEnum::Section->getModel())->toBe(Section::class)
        ->and(TypeEnum::Section->getLabel())->toBeString()
        ->and(LivewireComponentsEnum::ContentAssetsTable->getComponent())->toBe(SectionAssets::class)
        ->and(LivewireComponentsEnum::getComponents())->toContain(SectionAssets::class);
});

it('builds pending, expired, and trashed section alert messages', function (): void {
    $pendingWidget = new SectionAlertsWidget;
    $pendingWidget->record = Section::factory()->pending()->create();

    $expiredWidget = new SectionAlertsWidget;
    $expiredWidget->record = Section::factory()->expired()->create();

    $trashedSection = Section::factory()->create();
    $trashedSection->delete();

    $trashedWidget = new SectionAlertsWidget;
    $trashedWidget->record = $trashedSection;

    expect($pendingWidget->alerts())->toHaveKey('pending')
        ->and($expiredWidget->alerts())->toHaveKey('expired')
        ->and($trashedWidget->alerts())->toHaveKey('trashed')
        ->and($pendingWidget->record->publish_status)->toBe(PublishStatusEnum::pending)
        ->and($expiredWidget->record->publish_status)->toBe(PublishStatusEnum::expired);
});

it('normalizes section observer defaults and relation cache hooks', function (): void {
    $blueprint = Blueprint::factory()
        ->type(LayoutTypeEnum::Section)
        ->default()
        ->create();

    $section = Section::factory()->make([
        'blueprint_id' => null,
        'parent_id' => null,
    ]);

    $observer = new SectionObserver;
    $observer->creating($section);

    expect($section->blueprint_id)->toBe($blueprint->getKey())
        ->and($section->parent_id)->toBeNull();

    $observer->saving($section);
    $observer->deleting($section);
    $observer->deleted($section);
    $observer->restoring($section);
    $observer->restored($section);
});

it('declares section resource metadata', function (): void {
    expect(SectionResource::getModel())->toBe(Section::class)
        ->and(SectionResource::getResourceType())->toBe(ConfiguratorTypeEnum::Section)
        ->and(SectionResource::shouldRegisterNavigation())->toBeTrue()
        ->and(SectionResource::getGloballySearchableAttributes())->toBe(['name', 'translations.title'])
        ->and(SectionResource::getNavigationGroup())->toBeString()
        ->and(SectionResource::getNavigationParentItem())->toBeString()
        ->and(SectionResource::getNavigationLabel())->toBeString()
        ->and(SectionResource::getModelLabel())->toBeString()
        ->and(SectionResource::getPluralModelLabel())->toBeString()
        ->and(SectionResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(SectionResource::getRelations())->not->toBeEmpty()
        ->and(SectionResource::getWidgets())->toContain(SectionAlertsWidget::class)
        ->and(SectionResource::getGlobalSearchResultDetails(new class extends Model {}))->toBe([]);
});

it('covers section model render relations and local helpers', function (): void {
    $language = Language::factory()->english()->create();
    $blueprint = Blueprint::factory()
        ->type(LayoutTypeEnum::Section)
        ->create(['name' => 'Hero section']);
    $section = Section::factory()->create([
        'blueprint_id' => $blueprint->getKey(),
        'meta' => [
            'actions' => [
                ['label' => 'Start'],
            ],
            'related' => [],
        ],
    ]);
    $section->setRelation('blueprint', $blueprint);

    $relations = Section::getMorphRelations($language);

    $relations['linkedPage'](contentSectionsMorphBuilder());
    $relations['translation'](contentSectionsMorphBuilder());
    $section->registerMediaCollections();

    expect($relations)->toHaveKeys(['linkedPage', 'translation'])
        ->and($section->getBlueprint()->is($blueprint))->toBeTrue()
        ->and($section->site()->getForeignKeyName())->toBe('site_id')
        ->and($section->image()->getMorphType())->toBe('model_type')
        ->and($section->linkedPage()->getMorphType())->toBe('meta->linked_pageable_type')
        ->and($section->related()->getRelated())->toBeInstanceOf(Section::class)
        ->and($section->actions)->toBe([['label' => 'Start']])
        ->and($section->newQuery()->ordered()->toBase()->orders)->toHaveCount(2)
        ->and($section->getMediaCollection('image')?->name)->toBe('image');
});

function contentSectionsMorphBuilder(): Illuminate\Contracts\Database\Eloquent\Builder
{
    $builder = Mockery::mock(Illuminate\Contracts\Database\Eloquent\Builder::class);

    $builder->shouldReceive('with')->andReturnUsing(
        function (string|array $relations) use ($builder): Illuminate\Contracts\Database\Eloquent\Builder {
            foreach ((array) $relations as $relation) {
                if ($relation instanceof Closure) {
                    $relation($builder);
                }
            }

            return $builder;
        },
    );
    $builder->shouldReceive('when')->andReturnUsing(
        function (mixed $value, Closure $callback) use ($builder): Illuminate\Contracts\Database\Eloquent\Builder {
            if ($value) {
                $callback($builder);
            }

            return $builder;
        },
    );
    $builder->shouldReceive('orderByRaw')->andReturn($builder);
    $builder->shouldReceive('where')->andReturn($builder);

    return $builder;
}
