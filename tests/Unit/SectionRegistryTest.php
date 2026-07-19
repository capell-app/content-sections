<?php

declare(strict_types=1);

use Capell\BlockLibrary\Support\BlockRegistry;
use Capell\ContentSections\Actions\BuildSectionDemoDataAction;
use Capell\ContentSections\Actions\RegisterDefaultSectionsAction;
use Capell\ContentSections\Actions\RegisterSectionDefinitionProviderAction;
use Capell\ContentSections\Actions\ResolveRequestedSectionBlueprintAction;
use Capell\ContentSections\Actions\ResolveSectionComponentAction;
use Capell\ContentSections\Contracts\SectionDefinitionProvider;
use Capell\ContentSections\Data\SectionDefinitionData;
use Capell\ContentSections\Enums\LayoutTypeEnum;
use Capell\ContentSections\Enums\SectionConfiguratorEnum;
use Capell\ContentSections\Filament\Components\Forms\Content\BlueprintSelect;
use Capell\ContentSections\Filament\Components\Forms\Content\DetailsSchema;
use Capell\ContentSections\Filament\Configurators\Sections\AccordionSectionConfigurator;
use Capell\ContentSections\Support\SectionRegistry;
use Capell\Core\Models\Blueprint;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\DynamicComponent;

it('registers the main sections', function (): void {
    $registry = new SectionRegistry;

    RegisterDefaultSectionsAction::run($registry);

    expect(array_keys($registry->all()))->toContain(
        'accordion',
        'call_to_action',
        'comparison',
        'counter',
        'divider',
        'faq',
        'features',
        'logos',
        'pricing',
        'stats',
        'table',
        'tabs',
        'team',
        'timeline',
    );
});

it('exposes registered sections as typed content blocks', function (): void {
    $widgets = resolve(BlockRegistry::class);

    expect($widgets->get('section.accordion')?->view)->toBe('capell-block-library::blocks.catalog.accordion')
        ->and($widgets->get('section.accordion')?->category)->toBe('main')
        ->and($widgets->get('section.call_to_action')?->safeForPublicOutput)->toBeTrue();
});

it('guards against duplicate section keys', function (): void {
    $registry = new SectionRegistry;
    $definition = new SectionDefinitionData(
        key: 'accordion',
        label: 'Accordion',
        description: 'Accordion panels.',
        icon: Heroicon::OutlinedQueueList,
        group: 'main',
        configurator: SectionConfiguratorEnum::Accordion->value,
        component: 'capell-content-sections::section.widgets.accordion',
    );

    $registry->register($definition);
    $registry->register($definition);
})->throws(InvalidArgumentException::class);

it('resolves widget definitions from configurator classes and keys', function (): void {
    $registry = new SectionRegistry;

    RegisterDefaultSectionsAction::run($registry);

    expect($registry->getByConfigurator(AccordionSectionConfigurator::class)?->key)->toBe('accordion')
        ->and($registry->getByConfigurator(AccordionSectionConfigurator::getKey())?->key)->toBe('accordion');
});

it('registers section definitions from another package provider', function (): void {
    $registry = new SectionRegistry;
    $provider = new class implements SectionDefinitionProvider
    {
        /**
         * @return iterable<SectionDefinitionData>
         */
        public function definitions(): iterable
        {
            return [
                new SectionDefinitionData(
                    key: 'package_accordion',
                    label: 'Package accordion',
                    description: 'A package-owned section.',
                    icon: Heroicon::OutlinedQueueList,
                    group: 'package',
                    configurator: AccordionSectionConfigurator::class,
                    component: 'vendor-package::section.package-accordion',
                ),
            ];
        }
    };

    RegisterSectionDefinitionProviderAction::run($registry, $provider);

    expect($registry->get('package_accordion')?->component)->toBe('vendor-package::section.package-accordion')
        ->and($registry->getByConfigurator(AccordionSectionConfigurator::getKey())?->key)->toBe('package_accordion');
});

it('registers the same provider idempotently during dynamic package boot', function (): void {
    $registry = new SectionRegistry;

    RegisterDefaultSectionsAction::run($registry);
    $registeredSections = $registry->all();
    RegisterDefaultSectionsAction::run($registry);

    expect($registry->all())->toBe($registeredSections);
});

it('resolves the frontend component without string matching configurator names', function (): void {
    $registry = new SectionRegistry;
    $registry->register(new SectionDefinitionData(
        key: 'package_accordion',
        label: 'Package accordion',
        description: 'A package-owned section.',
        icon: Heroicon::OutlinedQueueList,
        group: 'package',
        configurator: AccordionSectionConfigurator::class,
        component: 'vendor-package::section.package-accordion',
    ));

    app()->instance(SectionRegistry::class, $registry);

    expect(ResolveSectionComponentAction::run(
        configurator: AccordionSectionConfigurator::getKey(),
        fallbackComponent: 'capell-content-sections::section.fallback',
    ))->toBe('vendor-package::section.package-accordion');
});

it('resolves requested screenshot section blueprints from query parameters', function (): void {
    $registry = new SectionRegistry;

    RegisterDefaultSectionsAction::run($registry);
    app()->instance(SectionRegistry::class, $registry);
    request()->query->set('section', 'accordion');

    $blueprint = ResolveRequestedSectionBlueprintAction::run();
    $admin = $blueprint->admin ?? [];

    expect($blueprint)->toBeInstanceOf(Blueprint::class)
        ->and($blueprint?->key)->toBe('accordion')
        ->and($admin['configurator'] ?? null)->toBe(AccordionSectionConfigurator::getKey());
});

it('creates the default section blueprint for generic create routes', function (): void {
    $registry = new SectionRegistry;

    RegisterDefaultSectionsAction::run($registry);
    app()->instance(SectionRegistry::class, $registry);

    expect(Blueprint::query()->where('type', LayoutTypeEnum::Section->value)->exists())->toBeFalse();

    $blueprint = ResolveRequestedSectionBlueprintAction::make()->defaultBlueprint();

    expect($blueprint->key)->toBe('content')
        ->and($blueprint->default)->toBeTrue()
        ->and(Blueprint::query()->where('type', LayoutTypeEnum::Section->value)->count())->toBe(1);
});

it('exposes inline blueprint creation from the section details schema', function (): void {
    $configurator = Schema::make()->operation('create');

    $blueprintSelect = collect(DetailsSchema::make($configurator))
        ->first(fn (mixed $component): bool => $component instanceof BlueprintSelect);

    expect($blueprintSelect)->toBeInstanceOf(BlueprintSelect::class)
        ->and($blueprintSelect->hasCreateOptionActionFormSchema())->toBeTrue();
});

it('renders every registered section demo component', function (): void {
    $registry = new SectionRegistry;

    RegisterDefaultSectionsAction::run($registry);
    app()->instance(SectionRegistry::class, $registry);
    view()->addNamespace('capell-block-library', __DIR__ . '/../../../block-library/resources/views');
    view()->addNamespace('capell-content-sections', __DIR__ . '/../../resources/views');
    Blade::anonymousComponentPath(__DIR__ . '/../../../block-library/resources/views', 'capell-block-library');
    registerBlockLibraryCatalogComponentsForDynamicRendering();
    Blade::anonymousComponentPath(__DIR__ . '/../Fixtures/components', 'capell');

    foreach (array_keys($registry->all()) as $key) {
        $data = BuildSectionDemoDataAction::run($key);
        $html = Blade::render(
            '<x-dynamic-component :component="$definition->component" :asset="$asset" :meta="$meta" :summary="$summary" :title="$title" :link-text="$linkText" :url="$url" />',
            $data,
        );

        expect($html)->toContain('section');
    }
});

it('localises section demo payload copy', function (): void {
    Lang::addLines([
        'demo.call_to_action.link_text' => 'Iniciar proyecto',
        'demo.call_to_action.actions.primary' => 'Iniciar proyecto',
        'demo.call_to_action.actions.secondary' => 'Ver ejemplos',
        'demo.accordion.items.publishing.heading' => '¿Con qué rapidez pueden actualizar contenido los editores?',
        'demo.accordion.items.publishing.content' => '<p>Los editores pueden actualizar paneles reutilizables una sola vez.</p>',
    ], 'es', 'capell-content-sections');

    app()->setLocale('es');

    $callToAction = BuildSectionDemoDataAction::run('call_to_action');
    $accordion = BuildSectionDemoDataAction::run('accordion');

    app()->setLocale('en');

    expect($callToAction['linkText'])->toBe('Iniciar proyecto')
        ->and(data_get($callToAction, 'meta.actions.0.label'))->toBe('Iniciar proyecto')
        ->and(data_get($callToAction, 'meta.actions.1.label'))->toBe('Ver ejemplos')
        ->and(data_get($accordion, 'meta.items.0.heading'))->toBe('¿Con qué rapidez pueden actualizar contenido los editores?')
        ->and(data_get($accordion, 'meta.items.0.content'))->toBe('<p>Los editores pueden actualizar paneles reutilizables una sola vez.</p>');
});

function registerBlockLibraryCatalogComponentsForDynamicRendering(): void
{
    foreach ([
        'accordion',
        'call-to-action',
        'comparison',
        'content',
        'counter',
        'divider',
        'faq',
        'features',
        'hero',
        'logos',
        'pricing',
        'stats',
        'table',
        'tabs',
        'team',
        'testimonial',
        'timeline',
    ] as $component) {
        Blade::component(
            'capell-block-library::blocks.catalog.' . $component,
            'capell-block-library::blocks.catalog.' . $component,
        );
    }

    clearContentSectionsDynamicComponentResolverCache();
}

function clearContentSectionsDynamicComponentResolverCache(): void
{
    $dynamicComponent = new ReflectionClass(DynamicComponent::class);

    $compiler = $dynamicComponent->getProperty('compiler');
    $compiler->setValue(null);

    $componentClasses = $dynamicComponent->getProperty('componentClasses');
    $componentClasses->setValue([]);
}
