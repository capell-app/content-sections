<?php

declare(strict_types=1);

use Capell\ContentSections\Filament\Resources\Sections\Pages\CreateSection;
use Capell\ContentSections\Filament\Resources\Sections\Pages\EditSection;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Livewire\Filament\LayoutBuilder;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('content');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

it('can save', function (): void {
    $content = Section::factory()->create();
    $blueprint = $content->getBlueprint();
    $newData = Section::factory()
        ->site(Site::factory()->create())
        ->parent(Section::factory()->create())
        ->make();

    livewire(EditSection::class, [
        'record' => $content->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaStateSet([
            'name' => $content->name,
            'blueprint_id' => $blueprint->getKey(),
            'parent_id' => $content->parent?->id,
            'site_id' => $content->site?->getKey(),
        ])
        ->fillForm([
            'name' => $newData->name,
            'parent_id' => $newData->parent->id,
            'site_id' => $newData->site->getKey(),
        ])
        ->assertSchemaStateSet([
            'name' => $newData->name,
            'parent_id' => $newData->parent->id,
            'site_id' => $newData->site->getKey(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($content->refresh())
        ->name->toBe($newData->name)
        ->blueprint_id->toBe($blueprint->getKey())
        ->parent_id->toBe($newData->parent->id)
        ->site_id->toBe($newData->site->getKey());
});

test('validates edit content', function (): void {
    $content = Section::factory()->create();

    livewire(EditSection::class, [
        'record' => $content->getRouteKey(),
    ])
        ->assertSuccessful()
        ->fillForm([
            'name' => null,
        ])
        ->call('save')
        ->assertHasFormErrors(['name' => 'required']);
});

it('can delete', function (): void {
    $content = Section::factory()->create();

    livewire(EditSection::class, [
        'record' => $content->getRouteKey(),
    ])
        ->assertSuccessful()
        ->callAction('delete')
        ->assertHasNoFormErrors();

    assertSoftDeleted($content, ['id' => $content->id]);
});

test('can create a section', function (): void {
    $newData = Section::factory()->make();
    $blueprint = $newData->getBlueprint();

    livewire(CreateSection::class)
        ->assertSuccessful()
        ->fillForm([
            'blueprint_id' => $blueprint->getKey(),
            'name' => $newData->name,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Section::class, [
        'blueprint_id' => $blueprint->getKey(),
        'name' => $newData->name,
    ]);
});

test('layout builder content editor mounts section edit form', function (): void {
    $widget = Widget::factory()->create(['key' => 'homepage-hero', 'name' => 'Homepage hero']);
    $content = Section::factory()->create(['name' => 'Homepage Hero: Page Object Slide']);
    WidgetAsset::factory()
        ->widget($widget)
        ->asset($content)
        ->occurrence(1)
        ->create(['order' => 1, 'meta' => ['variant' => 'default']]);

    $layout = Layout::factory()->create(['containers' => [
        'main' => ['widgets' => [
            ['widget_key' => $widget->key, 'occurrence' => 1],
        ]],
    ]]);

    livewire(LayoutBuilder::class, ['layout' => $layout])
        ->callAction('editWidgetAsset', data: [
            'meta' => ['variant' => 'updated'],
        ], arguments: [
            'containerKey' => 'main',
            'widgetIndex' => 0,
            'index' => 0,
            'type' => 'section',
        ])
        ->assertHasNoActionErrors();
});
