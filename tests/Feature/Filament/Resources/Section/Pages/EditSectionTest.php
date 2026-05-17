<?php

declare(strict_types=1);

use Capell\ContentSections\Filament\Resources\Sections\Pages\EditSection;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Site;
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

test('create action creates a section from the edit page', function (): void {
    $content = Section::factory()->create();
    $newData = Section::factory()->make();
    $blueprint = $newData->getBlueprint();

    livewire(EditSection::class, [
        'record' => $content->getRouteKey(),
    ])
        ->assertSuccessful()
        ->callAction('create', [
            'blueprint_id' => $blueprint->getKey(),
            'name' => $newData->name,
        ])
        ->assertHasNoFormErrors();

    assertDatabaseHas(Section::class, [
        'blueprint_id' => $blueprint->getKey(),
        'name' => $newData->name,
    ]);
});
