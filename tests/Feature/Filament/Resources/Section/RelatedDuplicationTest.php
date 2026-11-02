<?php

declare(strict_types=1);

namespace Capell\ContentSections\Tests\Feature\Filament\Resources\Section;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Filament\Components\Forms\PageSelect;
use Capell\Admin\Filament\Configurators\Pages\DefaultPageConfigurator;
use Capell\ContentSections\Actions\CreateHeroContentBlueprintAction;
use Capell\ContentSections\Actions\EnsureSectionBlueprintForKeyAction;
use Capell\ContentSections\Filament\Components\Forms\AssetsRepeater;
use Capell\ContentSections\Filament\Resources\Sections\Pages\CreateSection;
use Capell\ContentSections\Filament\Resources\Sections\Pages\EditSection;
use Capell\ContentSections\Filament\Resources\Sections\RelationManagers\SectionAssetsRelationManager;
use Capell\ContentSections\Models\Section;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Models\Translation;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Component;

use function Pest\Livewire\livewire;

use ReflectionMethod;
use RuntimeException;

uses(CreatesAdminUser::class);

/**
 * @return array<array-key, mixed>
 */
function relatedDuplicationIds(Section $section): array
{
    $meta = $section->meta;

    return is_array($meta) && is_array($meta['related'] ?? null) ? $meta['related'] : [];
}

function relatedDuplicationSection(mixed $id): Section
{
    return Section::query()->withoutGlobalScopes()->whereKey($id)->firstOrFail();
}

beforeEach(function (): void {
    test()->actingAsAdmin();
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');
});

it('does not create a section or media when a related copy is cancelled, removed or fails validation', function (string $outcome): void {
    $blueprint = CreateHeroContentBlueprintAction::run();
    $source = Section::factory()->blueprint($blueprint)->create(['name' => 'Source']);
    $media = $source->addMedia(UploadedFile::fake()->image('source.png'))->toMediaCollection(Section::BLOCK_MEDIA_COLLECTION, 'public');
    $parent = Section::factory()->blueprint($blueprint)->create(['name' => 'Parent', 'meta' => ['related' => [$source->id]]]);
    $sectionCount = Section::query()->count();
    $mediaCount = Media::query()->count();
    $files = Storage::disk('public')->allFiles();

    $editor = livewire(EditSection::class, ['record' => $parent->getRouteKey()])->assertSuccessful();
    $items = $editor->get('data.meta.related');
    $key = array_key_first($items);
    $editor->callAction(TestAction::make('clone')->schemaComponent('meta.related', schema: 'form')->arguments(['item' => $key]));

    expect(Section::query()->count())->toBe($sectionCount)
        ->and(Media::query()->count())->toBe($mediaCount)
        ->and(Storage::disk('public')->allFiles())->toBe($files);

    if ($outcome === 'removed') {
        $editor->set('data.meta.related', [$key => $items[$key]])->call('save')->assertHasNoFormErrors();
    } elseif ($outcome === 'invalid') {
        $editor->fillForm(['name' => ''])->call('save')->assertHasFormErrors(['name' => 'required']);
    }

    expect(Section::query()->count())->toBe($sectionCount)
        ->and(Media::query()->count())->toBe($mediaCount)
        ->and(Storage::disk('public')->allFiles())->toBe($files)
        ->and(relatedDuplicationIds($parent->refresh()))->toBe([$source->id])
        ->and($media->fresh()->model_id)->toBe($source->id)
        ->and(Storage::disk('public')->exists($media->getPathRelativeToRoot()))->toBeTrue();
})->with(['cancelled', 'removed', 'invalid']);

it('creates an independent related section and owned media when the parent is saved', function (): void {
    $blueprint = CreateHeroContentBlueprintAction::run();
    $source = Section::factory()->blueprint($blueprint)->create(['name' => 'Source']);
    $media = $source->addMedia(UploadedFile::fake()->image('source.png'))->toMediaCollection(Section::BLOCK_MEDIA_COLLECTION, 'public');
    $parent = Section::factory()->blueprint($blueprint)->create(['name' => 'Parent', 'meta' => ['related' => [$source->id]]]);

    $editor = livewire(EditSection::class, ['record' => $parent->getRouteKey()])->assertSuccessful();
    $items = $editor->get('data.meta.related');
    $key = array_key_first($items);
    $editor->callAction(TestAction::make('clone')->schemaComponent('meta.related', schema: 'form')->arguments(['item' => $key]));
    $editor->call('save')->assertHasNoFormErrors();

    $related = relatedDuplicationIds($parent->refresh());
    expect($related)->toHaveCount(2)->and($related[0])->toEqual($source->id)->and($related[1])->not->toEqual($source->id);
    $copy = relatedDuplicationSection($related[1]);
    $copiedMedia = $copy->getMedia(Section::BLOCK_MEDIA_COLLECTION)->sole();
    expect($copiedMedia->getKey())->not->toBe($media->getKey())
        ->and($copiedMedia->model_id)->toBe($copy->id)
        ->and($media->fresh()->model_id)->toBe($source->id)
        ->and(Storage::disk('public')->exists($copiedMedia->getPathRelativeToRoot()))->toBeTrue()
        ->and(Storage::disk('public')->exists($media->getPathRelativeToRoot()))->toBeTrue();
});

it('preserves both languages when a related section is cloned and saved', function (): void {
    $blueprint = CreateHeroContentBlueprintAction::run();
    $english = Language::factory()->create();
    $french = Language::factory()->create();
    $source = Section::factory()->blueprint($blueprint)
        ->withTranslations($english, ['title' => 'Original title', 'content' => 'Original content'])
        ->create(['name' => 'Source']);
    $source->translations()->create(['language_id' => $french->getKey(), 'title' => 'Titre original', 'content' => 'Contenu original']);
    $parent = Section::factory()->blueprint($blueprint)->create(['name' => 'Parent', 'meta' => ['related' => [$source->id]]]);

    $editor = livewire(EditSection::class, ['record' => $parent->getRouteKey()]);
    $key = array_key_first($editor->get('data.meta.related'));
    $editor->callAction(TestAction::make('clone')->schemaComponent('meta.related', schema: 'form')->arguments(['item' => $key]));
    $editor->call('save')->assertHasNoFormErrors();

    $related = relatedDuplicationIds($parent->fresh());
    expect($related)->toHaveCount(2);
    $copy = relatedDuplicationSection($related[1]);
    expect($copy->translations()->count())->toBe(2);
    $englishCopy = $copy->translations()->where('language_id', $english->getKey())->firstOrFail();
    $frenchCopy = $copy->translations()->where('language_id', $french->getKey())->firstOrFail();
    expect($englishCopy->getAttribute('title'))->toBe('Original title');
    expect($englishCopy->getAttribute('content'))->toBe('Original content');
    expect($frenchCopy->getAttribute('title'))->toBe('Titre original');
    expect($frenchCopy->getAttribute('content'))->toBe('Contenu original');

    // The cloned hero renders its own translated copy on the public view, and the source keeps its copy.
    view()->addNamespace('capell-block-library', dirname(__DIR__, 7) . '/block-library/resources/views');
    $renderHero = static fn (Section $section): string => view('capell-block-library::blocks.catalog.hero', [
        'asset' => $section,
        'title' => $section->translations->firstWhere('language_id', $english->getKey())?->title,
        'summary' => $section->translations->firstWhere('language_id', $english->getKey())?->content,
        'meta' => [],
        'attributes' => new ComponentAttributeBag,
    ])->render();
    $copyHtml = $renderHero($copy->load('translations'));
    expect($copyHtml)->toContain('<h1')->toContain('Original title')->toContain('Original content')
        ->and($copyHtml)->not->toContain('data-section-id')
        ->and($renderHero($source->load('translations')))->toContain('Original title');

    $copyEditor = livewire(EditSection::class, ['record' => $copy->getRouteKey()])->assertSuccessful();
    $formTranslations = $copyEditor->get('data.translations');
    $frenchKey = null;
    foreach ((array) $formTranslations as $key => $item) {
        if (is_array($item) && is_scalar($item['language_id'] ?? null) && (string) $item['language_id'] === (string) $french->getKey()) {
            $frenchKey = $key;
        }
    }
    expect($frenchKey)->not->toBeNull();
    $copyEditor->set('data.translations.' . $frenchKey . '.title', 'Titre modifié')
        ->set('data.translations.' . $frenchKey . '.content', 'Contenu modifié')
        ->call('save')->assertHasNoFormErrors();
    expect(relatedDuplicationSection($copy->getKey())->translations()->where('language_id', $french->getKey())->value('title'))->toBe('Titre modifié')
        ->and(relatedDuplicationSection($source->getKey())->translations()->where('language_id', $french->getKey())->value('title'))->toBe('Titre original')
        ->and($source->translations()->where('language_id', $french->getKey())->first()?->content)->toBe('Contenu original');
});

it('mounts the edit option modal for a related hero section', function (): void {
    $blueprint = CreateHeroContentBlueprintAction::run();
    $child = Section::factory()->blueprint($blueprint)->create(['name' => 'Child level']);
    $parent = Section::factory()->blueprint($blueprint)->create(['name' => 'Parent', 'meta' => ['related' => [$child->id]]]);

    $editor = livewire(EditSection::class, ['record' => $parent->getRouteKey()])->assertSuccessful();
    $key = array_key_first($editor->get('data.meta.related'));
    $editor->mountAction(TestAction::make('editOption')->schemaComponent('meta.related.' . $key . '.content_id', schema: 'form'));

    expect($editor->get('mountedActions'))->toHaveCount(1);
    $editor->assertMountedActionModalSee('Child level');
});

it('edits deeply related sections without losing the parent modal state', function (int $depth): void {
    $blueprint = CreateHeroContentBlueprintAction::run();
    $sections = [];
    $related = [];

    for ($level = $depth; $level >= 0; $level--) {
        $section = Section::factory()->blueprint($blueprint)->create([
            'name' => 'Section level ' . $level,
            'meta' => ['related' => $related],
        ]);
        $sections[$level] = $section;
        $related = [$section->id];
    }

    $editor = livewire(EditSection::class, ['record' => $sections[0]->getRouteKey()])->assertSuccessful();
    $key = array_key_first($editor->get('data.meta.related'));
    $editor->mountAction(TestAction::make('editOption')->schemaComponent('meta.related.' . $key . '.content_id', schema: 'form'));
    $editor->assertMountedActionModalSee('Section level 1');
    $editor->set('mountedActions.0.data.meta.icon', 'heroicon-o-heart');

    for ($level = 2; $level <= $depth; $level++) {
        $parentIndex = $level - 2;
        $items = $editor->get('mountedActions.' . $parentIndex . '.data.meta.related');
        expect($items)->toBeArray()->toHaveCount(1);
        $key = array_key_first($items);

        $editor->mountAction(TestAction::make('editOption')->schemaComponent(
            'related.related.' . $key . '.content_id',
            schema: 'mountedActionSchema' . $parentIndex,
        ));

        expect($editor->get('mountedActions'))->toHaveCount($level);
        $editor->assertMountedActionModalSee('Section level ' . $level);
        expect($editor->get('mountedActions.0.data.meta.icon'))->toBe('heroicon-o-heart');
    }

    expect($editor->get('mountedActions.' . ($depth - 1) . '.data.name'))->toBe('Section level ' . $depth)
        ->and($editor->get('mountedActions.' . ($depth - 1) . '.data.blueprint_id'))->toEqual($blueprint->getKey());

    $editor->set('mountedActions.' . ($depth - 1) . '.data.name', 'Updated deepest section')
        ->set('mountedActions.' . ($depth - 1) . '.data.meta.icon', 'heroicon-o-star')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($sections[$depth]->fresh()->name)->toBe('Updated deepest section')
        ->and($sections[$depth]->fresh()->blueprint_id)->toBe($blueprint->getKey())
        ->and($sections[$depth]->fresh()->meta['icon'] ?? null)->toBe('heroicon-o-star')
        ->and($sections[1]->fresh()->name)->toBe('Section level 1')
        ->and($editor->get('mountedActions.0.data.meta.icon'))->toBe('heroicon-o-heart')
        ->and(relatedDuplicationIds($sections[0]->fresh()))->toBe([$sections[1]->id]);
})->with([2, 3]);

it('creates a related section through the parent create form', function (): void {
    EnsureSectionBlueprintForKeyAction::run('content')->update(['default' => false]);
    $blueprint = CreateHeroContentBlueprintAction::run();
    $blueprint->update(['default' => true]);
    $editor = livewire(CreateSection::class)->set('data.blueprint_id', $blueprint->getKey());
    $editor->callAction(TestAction::make('add')->schemaComponent('meta.related', schema: 'form'));
    $key = array_key_first($editor->get('data.meta.related'));
    $editor->mountAction(TestAction::make('createOption')->schemaComponent('meta.related.' . $key . '.content_id', schema: 'form'));

    expect($editor->get('mountedActions'))->toHaveCount(1);
    $editor->set('mountedActions.0.data.name', 'Created related section')
        ->set('mountedActions.0.data.blueprint_id', $blueprint->getKey());
    $editor->callAction(TestAction::make('add')->schemaComponent('related.related', schema: 'mountedActionSchema0'));
    $nestedKey = array_key_first($editor->get('mountedActions.0.data.meta.related'));
    $editor->mountAction(TestAction::make('createOption')->schemaComponent('related.related.' . $nestedKey . '.content_id', schema: 'mountedActionSchema0'));
    expect($editor->get('mountedActions'))->toHaveCount(2);
    $editor->set('mountedActions.1.data.name', 'Created nested section')
        ->set('mountedActions.1.data.blueprint_id', $blueprint->getKey())
        ->callMountedAction()->assertHasNoActionErrors();

    $nested = Section::query()->where('name', 'Created nested section')->firstOrFail();
    expect($editor->get('mountedActions.0.data.meta.related.' . $nestedKey . '.content_id'))->toEqual($nested->getKey())
        ->and($editor->get('mountedActions.0.data.name'))->toBe('Created related section');
    $editor->callMountedAction()->assertHasNoActionErrors();

    $child = Section::query()->where('name', 'Created related section')->firstOrFail();
    expect($editor->get('data.meta.related.' . $key . '.content_id'))->toEqual($child->getKey())
        ->and(relatedDuplicationIds($child))->toEqual([$nested->getKey()]);
    $editor->set('data.name', 'Created parent')
        ->call('create')->assertHasNoFormErrors();

    $parent = Section::query()->where('name', 'Created parent')->firstOrFail();
    expect(relatedDuplicationIds($parent))->toEqual([$child->getKey()]);
});

it('keeps nested edits separate when a modal is cancelled and the parent is saved', function (): void {
    $blueprint = CreateHeroContentBlueprintAction::run();
    $child = Section::factory()->blueprint($blueprint)->create(['name' => 'Child before cancellation']);
    $parent = Section::factory()->blueprint($blueprint)->create(['name' => 'Parent before save', 'meta' => ['related' => [$child->getKey()]]]);
    $editor = livewire(EditSection::class, ['record' => $parent->getRouteKey()]);
    $key = array_key_first($editor->get('data.meta.related'));
    $editor->mountAction(TestAction::make('editOption')->schemaComponent('meta.related.' . $key . '.content_id', schema: 'form'))
        ->set('mountedActions.0.data.name', 'Cancelled child name')
        ->unmountAction()
        ->set('data.name', 'Saved parent name')
        ->call('save')->assertHasNoFormErrors();

    expect($child->fresh()->name)->toBe('Child before cancellation')
        ->and($parent->fresh()->name)->toBe('Saved parent name')
        ->and(relatedDuplicationIds($parent->fresh()))->toEqual([$child->getKey()]);
});

it('keeps the selected translation tab inside a nested edit modal', function (): void {
    $blueprint = CreateHeroContentBlueprintAction::run();
    $english = Language::factory()->create();
    $french = Language::factory()->create();
    $child = Section::factory()->blueprint($blueprint)
        ->withTranslations($english, ['title' => 'First language', 'content' => 'First content'])
        ->create(['name' => 'Translated child']);
    $child->translations()->create(['language_id' => $french->getKey(), 'title' => 'Second language', 'content' => 'Second content']);
    $parent = Section::factory()->blueprint($blueprint)->create(['name' => 'Parent', 'meta' => ['related' => [$child->getKey()]]]);
    $editor = livewire(EditSection::class, ['record' => $parent->getRouteKey()]);
    $key = array_key_first($editor->get('data.meta.related'));
    $editor->mountAction(TestAction::make('editOption')->schemaComponent('meta.related.' . $key . '.content_id', schema: 'form'));
    $translations = $editor->get('mountedActions.0.data.translations');
    expect($translations)->toBeArray()->toHaveCount(2);
    if (! is_array($translations)) {
        throw new RuntimeException('Expected translation state.');
    }
    $frenchKey = false;
    foreach ($translations as $key => $translation) {
        if (is_array($translation) && is_numeric($translation['language_id'] ?? null) && (int) $translation['language_id'] === $french->getKey()) {
            $frenchKey = $key;
            break;
        }
    }
    expect($frenchKey)->not->toBeFalse();

    $editor->set('mountedActions.0.data.activeTab', 2)
        ->set('mountedActions.0.data.translations.' . $frenchKey . '.title', 'Updated second language')
        ->callMountedAction()->assertHasNoActionErrors();
    expect($child->fresh()->translations()->where('language_id', $french->getKey())->value('title'))->toBe('Updated second language')
        ->and($child->fresh()->translations()->where('language_id', $english->getKey())->value('title'))->toBe('First language');
});

it('binds asset option forms to action state and uses a transaction', function (): void {
    CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
        class: DefaultPageConfigurator::class,
        group: 'Pages',
        name: 'default',
    ));
    Blueprint::factory()->page()->default()->create();
    $component = new ContentAssetOptionSchemaHarness;
    $component->data = ['asset_type' => 'page'];
    $fields = (new ReflectionMethod(AssetsRepeater::class, 'getFormSchema'))->invoke(null);
    if (! is_array($fields) || ! ($fields[1] ?? null) instanceof Select) {
        throw new RuntimeException('Expected asset select in the repeater schema.');
    }
    $select = $fields[1];
    $select->container(Schema::make($component)->statePath('data'));
    $schema = Schema::make($component)->statePath('mountedActions.0.data')->operation('createOption');
    $optionForm = $select->getCreateOptionActionForm($schema);

    expect($optionForm)->toBeInstanceOf(Schema::class);
    if (! $optionForm instanceof Schema) {
        throw new RuntimeException('Expected option schema.');
    }
    $optionForm->fill(['name' => 'Page draft']);
    expect(data_get($component->mountedActions, '0.data.name'))->toBe('Page draft')
        ->and($select->getCreateOptionAction()?->hasDatabaseTransactions())->toBeTrue();
});

final class ContentAssetOptionSchemaHarness extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<int, array<string, mixed>> */
    public array $mountedActions = [];
}

it('rolls back an asset when its option relationship save fails', function (): void {
    $language = Language::factory()->create();
    $component = new ContentAssetOptionSchemaHarness;
    $component->data = ['asset_type' => 'page'];
    $fields = (new ReflectionMethod(AssetsRepeater::class, 'getFormSchema'))->invoke(null);
    if (! is_array($fields) || ! ($fields[1] ?? null) instanceof Select) {
        throw new RuntimeException('Expected asset select in the repeater schema.');
    }
    $select = $fields[1];
    $select->container(Schema::make($component)->statePath('data'));
    $schema = Schema::make($component)->statePath('mountedActions.0.data')->model(Page::class)->components([
        Repeater::make('translations')->relationship()->schema([
            Hidden::make('language_id'),
            TextInput::make('title'),
        ]),
    ]);
    $schema->fill(['translations' => [['language_id' => $language->getKey(), 'title' => 'Page translation']]]);
    $page = Page::factory()->make(['name' => 'Partial asset']);
    $action = $select->getCreateOptionAction();
    if (! $action instanceof Action) {
        throw new RuntimeException('Expected create option action.');
    }
    Event::listen('eloquent.creating: ' . Translation::class, static function (): never {
        throw new RuntimeException('Asset relationship save failed');
    });

    $action->beginDatabaseTransaction();
    try {
        $select->evaluate($select->getCreateOptionUsing(), [
            'component' => $select,
            'data' => Arr::except($page->getAttributes(), ['created_at', 'updated_at']),
            'schema' => $schema,
            'form' => $schema,
        ]);
        $action->commitDatabaseTransaction();
        test()->fail('Expected asset relationship save to fail.');
    } catch (RuntimeException $exception) {
        $action->rollBackDatabaseTransaction();
        expect($exception->getMessage())->toBe('Asset relationship save failed');
    }

    expect(Page::query()->where('name', 'Partial asset')->exists())->toBeFalse();
});

it('binds relation manager asset creation to the mounted action state', function (): void {
    CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
        class: DefaultPageConfigurator::class,
        group: 'Pages',
        name: 'default',
    ));
    Blueprint::factory()->page()->default()->create();
    $section = Section::factory()->create();
    $type = (new ReflectionMethod(SectionAssetsRelationManager::class, 'getMorphToSelectType'))
        ->invoke(null, CapellCore::getAsset('page'), $section);
    if (! $type instanceof Type) {
        throw new RuntimeException('Expected asset select type.');
    }
    $component = new ContentAssetOptionSchemaHarness;
    $select = Select::make('asset_id')->container(Schema::make($component)->statePath('data'));
    $modifySelect = $type->getModifyKeySelectUsingCallback();
    if ($modifySelect === null) {
        throw new RuntimeException('Expected asset select callback.');
    }
    $select = $modifySelect($select);
    $schema = Schema::make($component)->statePath('mountedActions.0.data')->operation('createOption');
    $optionForm = $select->getCreateOptionActionForm($schema);

    expect($optionForm)->toBeInstanceOf(Schema::class);
    if (! $optionForm instanceof Schema) {
        throw new RuntimeException('Expected option schema.');
    }
    $optionForm->fill(['name' => 'Relation asset draft']);
    expect(data_get($component->mountedActions, '0.data.name'))->toBe('Relation asset draft');
});

it('saves relation manager option relationships when creating an asset', function (bool $failRelationship): void {
    CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
        class: DefaultPageConfigurator::class,
        group: 'Pages',
        name: 'default',
    ));
    Blueprint::factory()->page()->default()->create();
    $section = Section::factory()->create();
    $language = Language::factory()->create();
    $type = (new ReflectionMethod(SectionAssetsRelationManager::class, 'getMorphToSelectType'))
        ->invoke(null, CapellCore::getAsset('page'), $section);
    if (! $type instanceof Type) {
        throw new RuntimeException('Expected asset select type.');
    }
    $component = new ContentAssetOptionSchemaHarness;
    $select = Select::make('asset_id')->container(Schema::make($component)->statePath('data'));
    $modifySelect = $type->getModifyKeySelectUsingCallback();
    if ($modifySelect === null) {
        throw new RuntimeException('Expected asset select callback.');
    }
    $select = $modifySelect($select);
    $schema = $select->getCreateOptionActionForm(
        Schema::make($component)->statePath('mountedActions.0.data')->operation('createOption'),
    );
    expect($schema)->toBeInstanceOf(Schema::class);
    if (! $schema instanceof Schema) {
        throw new RuntimeException('Expected option schema.');
    }
    $schema->fill(['translations' => [['language_id' => $language->getKey(), 'title' => 'Relation asset translation', 'content' => 'Translation content', 'meta' => ['slug' => 'relation-asset']]]]);
    $page = Page::factory()->make(['name' => 'Relation manager asset']);
    if ($failRelationship) {
        Event::listen('eloquent.creating: ' . Translation::class, static function (): never {
            throw new RuntimeException('Relation asset save failed');
        });
    }

    $action = $select->getCreateOptionAction();
    if (! $action instanceof Action) {
        throw new RuntimeException('Expected create option action.');
    }
    $action->beginDatabaseTransaction();
    try {
        $id = $select->evaluate($select->getCreateOptionUsing(), [
            'component' => $select,
            'data' => Arr::except($page->getAttributes(), ['created_at', 'updated_at']),
            'schema' => $schema,
            'form' => $schema,
        ]);
        $action->commitDatabaseTransaction();
        if ($failRelationship) {
            test()->fail('Expected relation asset save to fail.');
        }
    } catch (RuntimeException $exception) {
        $action->rollBackDatabaseTransaction();
        expect($exception->getMessage())->toBe('Relation asset save failed');
    }

    if ($failRelationship) {
        expect(Page::query()->where('name', 'Relation manager asset')->exists())->toBeFalse();
    } else {
        $created = Page::query()->whereKey($id)->firstOrFail();
        expect($created->translations()->count())->toBe(1)
            ->and($created->translations()->where('language_id', $language->getKey())->value('title'))->toBe('Relation asset translation');
    }
})->with(['success' => false, 'relationship fails' => true]);

it('opens page creation from the section asset relation manager', function (): void {
    CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
        class: DefaultPageConfigurator::class,
        group: 'Pages',
        name: 'default',
    ));
    $blueprint = Blueprint::factory()->page()->default()->create();
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->getKey()]);
    $layout = Layout::factory()->create();
    $section = Section::factory()->create();
    $manager = livewire(SectionAssetsRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])->mountAction(TestAction::make(CreateAction::class)->table());
    $manager->set('mountedActions.0.data.asset_type', 'page');
    $manager->mountAction(TestAction::make('createOption')->schemaComponent('asset_id', schema: 'mountedActionSchema0'));
    expect($manager->get('mountedActions'))->toHaveCount(2);
    $manager->set('mountedActions.1.data.name', 'Created relation page')
        ->set('mountedActions.1.data.blueprint_id', $blueprint->getKey())
        ->set('mountedActions.1.data.site_id', $site->getKey())
        ->set('mountedActions.1.data.layout_id', $layout->getKey())
        ->set('mountedActions.1.data.translations', [[
            'language_id' => $language->getKey(),
            'title' => 'Created page title',
            'content' => 'Created page content',
            'meta' => ['slug' => 'created-relation-page'],
        ]])
        ->callMountedAction()->assertHasNoActionErrors();
    $created = Page::query()->where('name', 'Created relation page')->firstOrFail();
    expect($created->translations()->where('language_id', $language->getKey())->value('title'))->toBe('Created page title')
        ->and($manager->get('mountedActions.0.data.asset_id'))->toContain((string) $created->id);
    $manager->callMountedAction()->assertHasNoActionErrors();
    expect($section->assets()->where('asset_id', $created->getKey())->where('asset_type', 'page')->exists())->toBeTrue();
});

it('rejects relation manager asset creation without permission for the asset', function (): void {
    $section = Section::factory()->create();
    $page = Page::factory()->make(['name' => 'Denied relation asset']);
    $type = (new ReflectionMethod(SectionAssetsRelationManager::class, 'getMorphToSelectType'))
        ->invoke(null, CapellCore::getAsset('page'), $section);
    if (! $type instanceof Type) {
        throw new RuntimeException('Expected asset select type.');
    }
    $component = new ContentAssetOptionSchemaHarness;
    $select = Select::make('asset_id')->container(Schema::make($component)->statePath('data'));
    $modifySelect = $type->getModifyKeySelectUsingCallback();
    if ($modifySelect === null) {
        throw new RuntimeException('Expected asset select callback.');
    }
    $select = $modifySelect($select);
    $schema = Schema::make($component)->statePath('mountedActions.0.data')->model(Page::class);
    test()->actingAs(User::factory()->create());
    Gate::before(static fn ($user, string $ability, array $arguments): ?bool => $ability === 'create' && ($arguments[0] ?? null) === Page::class ? false : null);

    expect(fn (): mixed => $select->evaluate($select->getCreateOptionUsing(), [
        'component' => $select,
        'data' => Arr::except($page->getAttributes(), ['created_at', 'updated_at']),
        'schema' => $schema,
        'form' => $schema,
    ]))->toThrow(AuthorizationException::class);
    expect(Page::query()->where('name', 'Denied relation asset')->exists())->toBeFalse();
});

it('rejects duplicate page slugs when the section id matches the existing page id', function (): void {
    CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
        class: DefaultPageConfigurator::class,
        group: 'Pages',
        name: 'default',
    ));
    $blueprint = Blueprint::factory()->page()->default()->create();
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->getKey()]);
    $layout = Layout::factory()->create();
    $existing = Page::factory()->create(['site_id' => $site->getKey(), 'layout_id' => $layout->getKey(), 'blueprint_id' => $blueprint->getKey()]);
    $existing->translations()->create(['language_id' => $language->getKey(), 'title' => 'Existing page', 'content' => 'Existing content', 'meta' => ['slug' => 'taken-page']]);
    $section = Section::factory()->create(['id' => $existing->getKey()]);
    $manager = livewire(SectionAssetsRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])->mountAction(TestAction::make(CreateAction::class)->table());
    $manager->set('mountedActions.0.data.asset_type', 'page');
    $manager->mountAction(TestAction::make('createOption')->schemaComponent('asset_id', schema: 'mountedActionSchema0'));
    expect($manager->get('mountedActions'))->toHaveCount(2);
    $manager->set('mountedActions.1.data.name', 'Created relation page')
        ->set('mountedActions.1.data.blueprint_id', $blueprint->getKey())
        ->set('mountedActions.1.data.site_id', $site->getKey())
        ->set('mountedActions.1.data.layout_id', $layout->getKey())
        ->set('mountedActions.1.data.translations', [[
            'language_id' => $language->getKey(),
            'title' => 'Created page title',
            'content' => 'Created page content',
            'meta' => ['slug' => 'taken-page'],
        ]])
        ->callMountedAction()->assertHasErrors(['mountedActions.1.data.translations.0.meta.slug' => 'unique']);
    expect(Page::query()->where('name', 'Created relation page')->exists())->toBeFalse();
});

it('validates an embedded page edit against the selected page identity', function (bool $duplicate): void {
    CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
        class: DefaultPageConfigurator::class,
        group: 'Pages',
        name: 'default',
    ));
    $blueprint = Blueprint::factory()->page()->default()->create();
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->getKey()]);
    SiteDomain::factory()->site($site)->default()->create(['language_id' => $language->getKey()]);
    $layout = Layout::factory()->create();
    $page = Page::factory()->create(['site_id' => $site->getKey(), 'layout_id' => $layout->getKey(), 'blueprint_id' => $blueprint->getKey()]);
    $page->translations()->create(['language_id' => $language->getKey(), 'title' => 'Current title', 'content' => 'Current content', 'meta' => ['slug' => 'own-slug']]);
    $sibling = Page::factory()->create(['site_id' => $site->getKey(), 'layout_id' => $layout->getKey(), 'blueprint_id' => $blueprint->getKey()]);
    $sibling->translations()->create(['language_id' => $language->getKey(), 'title' => 'Other title', 'content' => 'Other content', 'meta' => ['slug' => 'other-slug']]);
    $section = Section::factory()->create(['id' => $sibling->getKey()]);

    $editor = livewire(EmbeddedPageEditHarness::class, ['pageId' => $page->getKey(), 'sectionId' => $section->getKey()]);
    $editor->mountAction(TestAction::make('editOption')->schemaComponent('page_id', schema: 'form'));
    expect($editor->get('mountedActions'))->toHaveCount(1);
    $translations = $editor->get('mountedActions.0.data.translations');
    $translationKey = array_key_first($translations);
    $editor->set('mountedActions.0.data.translations.' . $translationKey . '.meta.slug', $duplicate ? 'other-slug' : 'own-slug')
        ->callMountedAction();

    if ($duplicate) {
        $editor->assertHasErrors(['mountedActions.0.data.translations.' . $translationKey . '.meta.slug' => 'unique']);
    } else {
        $editor->assertHasNoActionErrors();
    }
    expect($page->fresh()->translations()->where('language_id', $language->getKey())->firstOrFail()->meta['slug'])->toBe('own-slug');
})->with(['own slug' => false, 'sibling slug' => true]);

final class EmbeddedPageEditHarness extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public int $sectionId;

    public function mount(int $pageId, int $sectionId): void
    {
        $this->sectionId = $sectionId;
        $schema = $this->getSchema('form');
        if ($schema === null) {
            throw new RuntimeException('Expected page form.');
        }
        $schema->fill(['page_id' => $pageId]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->model(Section::query()->findOrFail($this->sectionId))->statePath('data')->components([
            PageSelect::make('page_id')->withEditForm()
                ->updateOptionUsing(static function (array $data, Schema $schema): void {
                    $record = $schema->getRecord();
                    if (! $record instanceof Model) {
                        throw new RuntimeException('Expected page record.');
                    }
                    $record->update($data);
                }),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }} <x-filament-actions::modals /></div>';
    }
}

it('rejects asset option creation when the actor cannot create that asset', function (): void {
    $component = new ContentAssetOptionSchemaHarness;
    $component->data = ['asset_type' => 'page'];
    $fields = (new ReflectionMethod(AssetsRepeater::class, 'getFormSchema'))->invoke(null);
    if (! is_array($fields) || ! ($fields[1] ?? null) instanceof Select) {
        throw new RuntimeException('Expected asset select in the repeater schema.');
    }
    $select = $fields[1];
    $select->container(Schema::make($component)->statePath('data'));
    $schema = Schema::make($component)->statePath('mountedActions.0.data')->model(Page::class);
    $page = Page::factory()->make(['name' => 'Denied asset option']);
    test()->actingAs(User::factory()->create());
    Gate::before(static fn ($user, string $ability, array $arguments): ?bool => $ability === 'create' && ($arguments[0] ?? null) === Page::class ? false : null);

    expect(fn (): mixed => $select->evaluate($select->getCreateOptionUsing(), [
        'component' => $select,
        'data' => Arr::except($page->getAttributes(), ['created_at', 'updated_at']),
        'schema' => $schema,
        'form' => $schema,
    ]))->toThrow(AuthorizationException::class);
    expect(Page::query()->where('name', 'Denied asset option')->exists())->toBeFalse();
});
