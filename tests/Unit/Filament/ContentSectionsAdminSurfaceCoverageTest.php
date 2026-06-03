<?php

declare(strict_types=1);

use Capell\ContentSections\Enums\ActionLinkEnum;
use Capell\ContentSections\Filament\Components\Forms\ContentSelect;
use Capell\ContentSections\Filament\Configurators\Sections\TestimonialSectionConfigurator;
use Capell\ContentSections\Filament\Resources\Sections\SectionResource;
use Capell\ContentSections\Filament\Resources\Sections\Tables\SectionSelectionTable;
use Capell\ContentSections\Filament\Resources\Sections\Tables\SectionsTable;
use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Tests\Fixtures\ContentSectionsActionsRepeaterHarness;
use Capell\ContentSections\Tests\Fixtures\ContentSectionsAssetsRepeaterHarness;
use Capell\ContentSections\Tests\Fixtures\ContentSectionsSchemaLivewireHarness;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section as FilamentSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Mockery\MockInterface;

it('builds testimonial section schemas for create and edit flows', function (): void {
    $configurator = new TestimonialSectionConfigurator;

    $createOptionComponents = $configurator->make(contentSectionsSchema('createOption'));
    $editOptionComponents = $configurator->make(contentSectionsSchema('editOption'));
    $createComponents = $configurator->make(contentSectionsSchema('create'));
    $editComponents = $configurator->make(contentSectionsSchema('edit'));

    expect(contentSectionsComponentNames($createOptionComponents))->toContain('translations', 'image', 'company', 'position')
        ->and(contentSectionsComponentNames($editOptionComponents))->toContain(
            'translations',
            'image',
            'company',
            'position',
        )
        ->and(collect(flattenContentSectionsComponents($createOptionComponents))->contains(
            fn (mixed $component): bool => $component instanceof Grid,
        ))->toBeTrue()
        ->and(collect(flattenContentSectionsComponents($createOptionComponents))->contains(
            fn (mixed $component): bool => $component instanceof Select,
        ))->toBeTrue()
        ->and(collect(flattenContentSectionsComponents($editOptionComponents))->contains(
            fn (mixed $component): bool => $component instanceof FilamentSection,
        ))->toBeTrue()
        ->and($createComponents)->not->toBeEmpty()
        ->and($editComponents)->not->toBeEmpty();
});

it('configures section selection tables with scoped query filters', function (): void {
    $site = Site::factory()->create(['name' => 'Primary']);
    $sectionBlueprint = Blueprint::factory()->create([
        'name' => 'Reusable section',
        'type' => 'section',
        'status' => true,
    ]);
    $pageBlueprint = Blueprint::factory()->create([
        'name' => 'Public page',
        'type' => 'page',
        'status' => true,
    ]);

    $includedSection = Section::factory()->create([
        'site_id' => $site->getKey(),
        'blueprint_id' => $sectionBlueprint->getKey(),
    ]);
    $excludedSection = Section::factory()->create([
        'site_id' => null,
        'blueprint_id' => $sectionBlueprint->getKey(),
    ]);

    $table = SectionSelectionTable::configure(
        Table::make(contentSectionsTableLivewire(['excludeIds' => [$excludedSection->getKey()]])),
    );

    $query = $table->getQuery();

    throw_unless($query instanceof Builder, RuntimeException::class, 'Expected section selection table query.');

    expect($table->getColumns())->toHaveCount(4)
        ->and($table->getFilters())->toHaveCount(2)
        ->and($table->getFilters())->each->toBeInstanceOf(SelectFilter::class)
        ->and($query)->toBeInstanceOf(Builder::class)
        ->and($query->whereKey($includedSection->getKey())->exists())->toBeTrue()
        ->and($query->whereKey($excludedSection->getKey())->exists())->toBeFalse()
        ->and($pageBlueprint->exists)->toBeTrue();
});

it('scopes section resource and global search queries to global and assigned site records', function (): void {
    $assignedSite = Site::factory()->create();
    $hiddenSite = Site::factory()->create();
    $globalSection = Section::factory()->create(['site_id' => null]);
    $assignedSection = Section::factory()->site($assignedSite)->create();
    Section::factory()->site($hiddenSite)->create();

    auth()->setUser(contentSectionsScopedUser(collect([(int) $assignedSite->getKey()])));

    expect(SectionResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$globalSection->getKey(), $assignedSection->getKey()])
        ->and(SectionResource::getGlobalSearchEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$globalSection->getKey(), $assignedSection->getKey()]);
});

it('scopes section selection table rows and site filter options to the current actor', function (): void {
    $assignedSite = Site::factory()->create(['name' => 'Assigned Site']);
    $hiddenSite = Site::factory()->create(['name' => 'Hidden Site']);
    $globalSection = Section::factory()->create(['site_id' => null]);
    $assignedSection = Section::factory()->site($assignedSite)->create();
    Section::factory()->site($hiddenSite)->create();

    auth()->setUser(contentSectionsScopedUser(collect([(int) $assignedSite->getKey()])));

    $selectionTable = SectionSelectionTable::configure(
        Table::make(contentSectionsTableLivewire()),
    );
    $sectionsTable = SectionsTable::configure(
        Table::make(contentSectionsTableLivewire()),
    );
    $siteFilter = collect($sectionsTable->getFilters())
        ->first(fn (mixed $filter): bool => $filter instanceof SelectFilter && $filter->getName() === 'site_id');

    expect($selectionTable->getQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$globalSection->getKey(), $assignedSection->getKey()])
        ->and($siteFilter)->toBeInstanceOf(SelectFilter::class)
        ->and($siteFilter?->getOptions())->toHaveKey($assignedSite->getKey())
        ->and($siteFilter?->getOptions())->not->toHaveKey($hiddenSite->getKey());
});

it('labels content action repeater items from the selected higher level target', function (): void {
    $page = Page::factory()->create(['name' => 'Pricing']);
    $repeater = ContentSectionsActionsRepeaterHarness::make('actions');
    $repeater->rawState([
        'blank' => [],
        'page' => [
            'type' => ActionLinkEnum::Page->value,
            'pageable_type' => $page->getMorphClass(),
            'pageable_id' => $page->getKey(),
        ],
        'link' => [
            'type' => ActionLinkEnum::Link->value,
            'url' => 'https://example.test/pricing',
        ],
        'public-action' => [
            'type' => ActionLinkEnum::PublicAction->value,
            'public_action_key' => 'download-guide',
            'label' => 'Guide',
        ],
    ]);

    expect($repeater->getItemLabel('blank'))->toBeNull()
        ->and($repeater->getItemLabel('page'))->toContain('Pricing')
        ->and($repeater->getItemLabel('link'))->toContain('https://example.test/pricing')
        ->and($repeater->getItemLabel('public-action'))->toBe('Guide')
        ->and($repeater->isCollapsed(contentSectionsStateSchema(['pageable_id' => $page->getKey()])))->toBeTrue()
        ->and($repeater->isCollapsed(contentSectionsStateSchema(['url' => 'https://example.test'])))->toBeTrue()
        ->and($repeater->isCollapsed(contentSectionsStateSchema([])))->toBeFalse();
});

it('adds and edits content section widget assets through the repeater action workflow', function (): void {
    $assetPage = Page::factory()->create(['name' => 'Asset page']);
    $component = ContentSectionsAssetsRepeaterHarness::make('assets');
    $component->container(contentSectionsSchema('edit'));
    $component->generateUuidUsing(static fn (): string => 'asset-row');

    $component->getAddAssetAction()->call([
        'component' => $component,
        'arguments' => [
            'asset_type' => 'page',
            'asset_id' => $assetPage->getKey(),
        ],
    ]);

    $editAction = $component->getExtraItemActions()['edit_asset'];
    $editAction
        ->schemaComponent($component)
        ->arguments(['item' => 'asset-row']);

    expect($component->rawState)->toHaveKey('asset-row')
        ->and($component->rawState['asset-row'])->toMatchArray([
            'asset_type' => 'page',
            'asset_id' => $assetPage->getKey(),
        ])
        ->and($component->lastChildSchema)->toBeInstanceOf(Schema::class)
        ->and($component->afterStateUpdatedCalled)->toBeTrue()
        ->and($component->partiallyRendered)->toBeTrue()
        ->and($component->collapsedCalled)->toBeTrue()
        ->and($editAction->isVisible())->toBeTrue()
        ->and($editAction->getTooltip())->toContain('page');

    $component->rawState['asset-row']['asset_id'] = $assetPage;

    expect($editAction->getUrl())->toBeString();

    $numericComponent = ContentSectionsAssetsRepeaterHarness::make('assets');
    $numericComponent->container(contentSectionsSchema('edit'));
    $numericComponent->generateUuidUsing(false);

    $numericComponent->getAddAssetAction()->call([
        'component' => $numericComponent,
        'arguments' => [
            'asset_type' => 'page',
            'asset_id' => $assetPage->getKey(),
        ],
    ]);

    expect($numericComponent->rawState[0]['asset_id'])->toBe($assetPage->getKey());
});

it('loads content select options through site content type parent and admin query filters', function (): void {
    $primarySite = Site::factory()->create(['name' => 'Primary Site']);
    $otherSite = Site::factory()->create(['name' => 'Other Site']);
    $parentBlueprint = Blueprint::factory()->create([
        'key' => 'collection',
        'type' => 'section',
        'status' => true,
    ]);
    $childBlueprint = Blueprint::factory()->create([
        'key' => 'teaser',
        'type' => 'section',
        'status' => true,
    ]);
    $otherParentBlueprint = Blueprint::factory()->create([
        'key' => 'other-collection',
        'type' => 'section',
        'status' => true,
    ]);

    $parent = Section::factory()->create([
        'name' => 'Parent Collection',
        'site_id' => $primarySite->getKey(),
        'blueprint_id' => $parentBlueprint->getKey(),
    ]);
    $matchingSection = Section::factory()->parent($parent)->create([
        'name' => 'Alpha Launch Teaser Content Section',
        'site_id' => $primarySite->getKey(),
        'blueprint_id' => $childBlueprint->getKey(),
    ]);
    $excludedByCallback = Section::factory()->parent($parent)->create([
        'name' => 'Excluded Alpha Teaser',
        'site_id' => $primarySite->getKey(),
        'blueprint_id' => $childBlueprint->getKey(),
    ]);
    $wrongSiteSection = Section::factory()->parent($parent)->create([
        'name' => 'Alpha Other Site Teaser',
        'site_id' => $otherSite->getKey(),
        'blueprint_id' => $childBlueprint->getKey(),
    ]);
    $wrongParent = Section::factory()->create([
        'name' => 'Wrong Parent',
        'site_id' => $primarySite->getKey(),
        'blueprint_id' => $otherParentBlueprint->getKey(),
    ]);
    $wrongParentSection = Section::factory()->parent($wrongParent)->create([
        'name' => 'Alpha Wrong Parent Teaser',
        'site_id' => $primarySite->getKey(),
        'blueprint_id' => $childBlueprint->getKey(),
    ]);

    $select = ContentSelect::make('content_id')
        ->container(contentSectionsSchema('edit'))
        ->contentType(static fn (): string => 'teaser')
        ->parentContentType('collection')
        ->modifySelectOptionsQueryUsing(
            static fn (Builder $query): Builder => $query->where('sections.name', 'not like', 'Excluded%'),
        );

    $siteOptions = invokeContentSelectOptions($select, $primarySite->getKey(), 'Alpha');
    $allSiteOptions = invokeContentSelectOptions($select);
    $optionLabel = evaluateContentSelectCallback($select, 'getOptionLabelUsing', [
        'value' => $matchingSection->getKey(),
    ]);
    $get = Mockery::mock(Get::class);
    $get->shouldReceive('__invoke')->with('site_id')->andReturn($primarySite->getKey());
    $searchResults = evaluateContentSelectCallback($select, 'getSearchResultsUsing', [
        'search' => 'Alpha',
    ], [
        Get::class => $get,
    ]);

    expect($siteOptions)->toHaveKey($matchingSection->getKey())
        ->and($siteOptions)->not->toHaveKey($excludedByCallback->getKey())
        ->and($siteOptions)->not->toHaveKey($wrongSiteSection->getKey())
        ->and($siteOptions)->not->toHaveKey($wrongParentSection->getKey())
        ->and((string) $siteOptions[$matchingSection->getKey()])->toContain('Alpha Launch Teaser')
        ->and((string) $allSiteOptions[$matchingSection->getKey()])->toContain('Primary Site')
        ->and($optionLabel)->toBe('Alpha Launch Teaser Content Section')
        ->and($searchResults)->toHaveKey($matchingSection->getKey());
});

it('scopes content select options selected records and labels to the current actor', function (): void {
    $assignedSite = Site::factory()->create(['name' => 'Assigned Site']);
    $hiddenSite = Site::factory()->create(['name' => 'Hidden Site']);
    $globalSection = Section::factory()->create([
        'name' => 'Global Section',
        'site_id' => null,
    ]);
    $assignedSection = Section::factory()->site($assignedSite)->create([
        'name' => 'Assigned Section',
    ]);
    $hiddenSection = Section::factory()->site($hiddenSite)->create([
        'name' => 'Hidden Section',
    ]);

    auth()->setUser(contentSectionsScopedUser(collect([(int) $assignedSite->getKey()])));

    $select = ContentSelect::make('content_id')
        ->container(contentSectionsSchema('edit'));
    $options = invokeContentSelectOptions($select);
    $searchResults = evaluateContentSelectCallback($select, 'getSearchResultsUsing', [
        'search' => 'Section',
    ], [
        Get::class => contentSectionsSiteIdGetter(null),
    ]);
    $hiddenLabel = evaluateContentSelectCallback($select, 'getOptionLabelUsing', [
        'value' => $hiddenSection->getKey(),
    ]);

    expect($options)->toHaveKey($globalSection->getKey())
        ->and($options)->toHaveKey($assignedSection->getKey())
        ->and($options)->not->toHaveKey($hiddenSection->getKey())
        ->and($searchResults)->toHaveKey($globalSection->getKey())
        ->and($searchResults)->toHaveKey($assignedSection->getKey())
        ->and($searchResults)->not->toHaveKey($hiddenSection->getKey())
        ->and($hiddenLabel)->toBe('')
        ->and(evaluateContentSelectCallback($select->withEditForm(), 'getSelectedRecordUsing', [
            'state' => $assignedSection->getKey(),
        ]))->toBeInstanceOf(Section::class)
        ->and(fn (): mixed => evaluateContentSelectCallback($select, 'getSelectedRecordUsing', [
            'state' => $hiddenSection->getKey(),
        ]))->toThrow(ModelNotFoundException::class);
});

it('denies inline content select updates for inaccessible records and submitted site references', function (): void {
    $assignedSite = Site::factory()->create();
    $hiddenSite = Site::factory()->create();
    $assignedSection = Section::factory()->site($assignedSite)->create(['name' => 'Assigned Section']);
    $hiddenSection = Section::factory()->site($hiddenSite)->create(['name' => 'Hidden Section']);

    auth()->setUser(contentSectionsScopedUser(collect([(int) $assignedSite->getKey()])));

    $select = ContentSelect::make('content_id')
        ->container(contentSectionsSchema('edit'))
        ->withEditForm();

    expect(fn (): mixed => evaluateContentSelectCallback($select, 'updateOptionUsing', [
        'data' => ['name' => 'Updated hidden section'],
        'configurator' => contentSectionsSchema('edit')->record($hiddenSection),
    ]))->toThrow(AuthorizationException::class)
        ->and(fn (): mixed => evaluateContentSelectCallback($select, 'updateOptionUsing', [
            'data' => ['site_id' => $hiddenSite->getKey()],
            'configurator' => contentSectionsSchema('edit')->record($assignedSection),
        ]))->toThrow(AuthorizationException::class)
        ->and($hiddenSection->fresh()->name)->toBe('Hidden Section')
        ->and($assignedSection->fresh()->site_id)->toBe($assignedSite->getKey());
});

it('scopes parent filter options to global and assigned site records', function (): void {
    $assignedSite = Site::factory()->create();
    $hiddenSite = Site::factory()->create();
    $globalParent = Section::factory()->create(['name' => 'Global Parent', 'site_id' => null]);
    $assignedParent = Section::factory()->site($assignedSite)->create(['name' => 'Assigned Parent']);
    $hiddenParent = Section::factory()->site($hiddenSite)->create(['name' => 'Hidden Parent']);
    Section::factory()->parent($globalParent)->create(['site_id' => null]);
    Section::factory()->parent($assignedParent)->site($assignedSite)->create();
    Section::factory()->parent($hiddenParent)->site($hiddenSite)->create();

    auth()->setUser(contentSectionsScopedUser(collect([(int) $assignedSite->getKey()])));

    $allOptions = invokeParentSectionOptions(null, null);
    $assignedSiteOptions = invokeParentSectionOptions($assignedSite->getKey(), null);
    $hiddenSiteOptions = invokeParentSectionOptions($hiddenSite->getKey(), null);
    $hiddenSearchOptions = invokeParentSectionOptions(null, null, 'Hidden');

    expect($allOptions)->toHaveKey($globalParent->getKey())
        ->and($allOptions)->toHaveKey($assignedParent->getKey())
        ->and($allOptions)->not->toHaveKey($hiddenParent->getKey())
        ->and($assignedSiteOptions)->toHaveKey($assignedParent->getKey())
        ->and($assignedSiteOptions)->not->toHaveKey($hiddenParent->getKey())
        ->and($hiddenSiteOptions)->toBe([])
        ->and($hiddenSearchOptions)->toBe([]);
});

/**
 * @param  array<int, mixed>  $components
 * @return array<int, mixed>
 */
function flattenContentSectionsComponents(array $components): array
{
    $flattenedComponents = [];

    foreach ($components as $component) {
        $flattenedComponents[] = $component;
        if (! is_object($component)) {
            continue;
        }

        if (! method_exists($component, 'getDefaultChildComponents')) {
            continue;
        }

        $childComponents = $component->getDefaultChildComponents();

        if (is_array($childComponents)) {
            array_push($flattenedComponents, ...flattenContentSectionsComponents($childComponents));
        }
    }

    return $flattenedComponents;
}

/**
 * @param  array<int, mixed>  $components
 * @return array<int, string>
 */
function contentSectionsComponentNames(array $components): array
{
    return collect(flattenContentSectionsComponents($components))
        ->filter(fn (mixed $component): bool => is_object($component) && method_exists($component, 'getName'))
        ->map(fn (mixed $component): string => $component->getName())
        ->values()
        ->all();
}

/**
 * @return array<array-key, mixed>
 */
function invokeContentSelectOptions(ContentSelect $select, ?int $siteId = null, ?string $search = null): array
{
    $reflection = new ReflectionMethod(ContentSelect::class, 'getContentOptions');

    /** @var array<array-key, mixed> $options */
    $options = $reflection->invoke($select, $siteId, $search);

    return $options;
}

/**
 * @return array<array-key, mixed>
 */
function invokeParentSectionOptions(null|int|string $siteId, ?int $languageId, ?string $search = null): array
{
    $reflection = new ReflectionMethod(SectionsTable::class, 'parentSectionOptions');

    /** @var array<array-key, mixed> $options */
    $options = $reflection->invoke(null, $siteId, $languageId, $search);

    return $options;
}

function contentSectionsSiteIdGetter(?int $siteId): Get&MockInterface
{
    $get = Mockery::mock(Get::class);
    $get->shouldReceive('__invoke')->with('site_id')->andReturn($siteId);

    return $get;
}

/**
 * @param  array<string, mixed>  $parameters
 * @param  array<string, mixed>  $typedInjections
 */
function evaluateContentSelectCallback(ContentSelect $select, string $property, array $parameters, array $typedInjections = []): mixed
{
    $reflection = new ReflectionProperty(Select::class, $property);
    $closure = $reflection->getValue($select);

    expect($closure)->toBeInstanceOf(Closure::class);

    return $select->evaluate($closure, $parameters, [
        ContentSelect::class => $select,
        Select::class => $select,
        ...$typedInjections,
    ]);
}

/**
 * @param  array<string, mixed>  $tableArguments
 */
function contentSectionsTableLivewire(array $tableArguments = []): HasTable&MockInterface
{
    $livewire = Mockery::mock(HasTable::class);
    $livewire->shouldReceive('makeFilamentTranslatableContentDriver')->andReturn(null);
    $livewire->shouldReceive('getTableArguments')->andReturn($tableArguments);

    return $livewire;
}

function contentSectionsSchema(string $operation): Schema
{
    return Schema::make(new ContentSectionsSchemaLivewireHarness)->operation($operation);
}

/**
 * @param  Collection<int, int>  $assignedSiteIds
 */
function contentSectionsScopedUser(Collection $assignedSiteIds): Authenticatable
{
    $user = new class extends Authenticatable
    {
        /** @use HasFactory<Factory<static>> */
        use HasFactory;

        /** @var Collection<int, int> */
        public Collection $assignedSiteIds;

        protected $table = 'users';

        public function isGlobalAdmin(): bool
        {
            return false;
        }

        public function checkPermissionTo(string $permission): bool
        {
            return true;
        }

        public function hasRole(string $role): bool
        {
            return false;
        }

        /** @return Collection<int, int> */
        public function getAssignedSiteIds(): Collection
        {
            return $this->assignedSiteIds;
        }
    };

    $user->assignedSiteIds = $assignedSiteIds;

    return $user;
}

/**
 * @param  array<string, mixed>  $state
 */
function contentSectionsStateSchema(array $state): Schema
{
    $harness = new ContentSectionsSchemaLivewireHarness;
    $schema = Schema::make($harness)
        ->operation('edit')
        ->statePath('state');

    $schema->rawState($state);

    return $schema;
}
