<?php

declare(strict_types=1);

namespace Capell\ContentSections\Tests\Feature\Filament\Resources\Section;

use Capell\ContentSections\Actions\CreateHeroContentBlueprintAction;
use Capell\ContentSections\Enums\SectionConfiguratorEnum;
use Capell\ContentSections\Filament\Resources\Sections\Pages\EditSection;
use Capell\ContentSections\Models\Section;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('content');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

it('edits the hero content via Filament', function (): void {
    $blueprint = CreateHeroContentBlueprintAction::run();
    $section = Section::factory()->blueprint($blueprint)
        ->state([
            'name' => 'Hero Content',
        ])
        ->create();

    livewire(EditSection::class, [
        'record' => $section->getRouteKey(),
    ])
        ->assertSuccessful()
        ->fillForm([
            'name' => 'Updated Hero Content',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $section->refresh();

    expect($section)
        ->toBeInstanceOf(Section::class)
        ->name->toBe('Updated Hero Content');

    $blueprint = $section->blueprint;

    expect($blueprint->key)
        ->toBe('hero');

    expect($blueprint->admin['configurator'] ?? null)
        ->toBe(SectionConfiguratorEnum::Hero->name);
});

it('validates edit hero content', function (): void {
    $blueprint = CreateHeroContentBlueprintAction::run();
    $section = Section::factory()->blueprint($blueprint)
        ->state([
            'name' => 'Hero Content',
        ])
        ->create();

    livewire(EditSection::class, [
        'record' => $section->getRouteKey(),
    ])
        ->assertSuccessful()
        ->fillForm([
            'name' => '',
        ])
        ->call('save')
        ->assertHasAllFormErrors([
            'name' => 'required',
        ]);
});
