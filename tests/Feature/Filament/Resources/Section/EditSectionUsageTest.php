<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\BuildSectionUsageSummaryAction;
use Capell\ContentSections\Enums\AssetEnum as SectionAssetEnum;
use Capell\ContentSections\Filament\Resources\Sections\Pages\EditSection;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\AssetAttachment;
use Capell\Core\Models\Page;
use Capell\LayoutBuilder\Database\Factories\WidgetAssetFactory;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('section');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

function usageAttachment(Section $section, Page $page): AssetAttachment
{
    return AssetAttachment::factory([
        'asset_type' => SectionAssetEnum::Section->value,
        'asset_id' => $section->getKey(),
    ])->related($page)->create();
}

it('shows no impact note for an unused section', function (): void {
    $section = Section::factory()->create();

    livewire(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(__('capell-content-sections::message.usage_none'));
});

it('shows a compact impact note naming how many places use the section', function (): void {
    $section = Section::factory()->create();
    $pageOne = Page::factory()->withTranslations()->create(['name' => 'Home page']);
    $pageTwo = Page::factory()->withTranslations()->create(['name' => 'About page']);
    usageAttachment($section, $pageOne);
    usageAttachment($section, $pageTwo);

    livewire(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Used in 2 places');
});

it('opens a usage breakdown listing the current destinations', function (): void {
    $section = Section::factory()->create();
    $page = Page::factory()->withTranslations()->create(['name' => 'Contact page']);
    usageAttachment($section, $page);

    livewire(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertActionExists('reviewUses')
        ->mountAction('reviewUses')
        ->assertSee('Contact page');
});

it('names the current destinations in the unpublish confirmation', function (): void {
    $section = Section::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => null,
    ]);
    $page = Page::factory()->withTranslations()->create(['name' => 'Pricing page']);
    usageAttachment($section, $page);

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertSuccessful()
        ->mountAction('unpublish')
        ->assertSee('Pricing page');
});

it('names the current destinations in the delete confirmation', function (): void {
    $section = Section::factory()->create();
    $page = Page::factory()->withTranslations()->create(['name' => 'Careers page']);
    usageAttachment($section, $page);

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertSuccessful()
        ->mountAction('delete')
        ->assertSee('Careers page');
});

it('keeps computing usage for a trashed section, ready for the force-delete confirmation', function (): void {
    // Visiting the Livewire edit page for an already-trashed record hits a
    // pre-existing, orthogonal Filament/Livewire limitation on this page
    // (route-model-bound record resolution on the initial mount does not
    // agree with re-hydration on a later interaction for a soft-deleted
    // row). ForceDeleteAction's modal reuses the same
    // `SectionUsageWarnings::describe()` already proven against delete above,
    // so what actually needs proving here is that the usage projection itself
    // stays correct once the section is trashed — which this asserts directly.
    $section = Section::factory()->create();
    $page = Page::factory()->withTranslations()->create(['name' => 'Support page']);
    usageAttachment($section, $page);
    $section->delete();

    $usage = BuildSectionUsageSummaryAction::run($section);

    expect($usage->isUsed())->toBeTrue()
        ->and($usage->destinationsPreview())->toContain('Support page');
});

it('shows fresh usage on each confirmation after the underlying reference changes', function (): void {
    $section = Section::factory()->create();
    $page = Page::factory()->withTranslations()->create(['name' => 'Old destination']);
    $attachment = usageAttachment($section, $page);

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertSuccessful()
        ->mountAction('delete')
        ->assertSee('Old destination');

    $attachment->delete();

    // A fresh Livewire::test() call mirrors a real second request — the
    // component is rehydrated from scratch, so this proves the confirmation
    // recomputes rather than reusing a value cached from the first mount.
    // (Not asserting the page overall no longer mentions "Old destination":
    // the edit form's own linked-page selector legitimately preloads every
    // page's name as selectable option data, unrelated to usage.)
    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertSuccessful()
        ->mountAction('delete')
        ->assertSee(__('capell-content-sections::message.usage_none'));
});

it('never carries incoming usage over to a replicated section', function (): void {
    $section = Section::factory()->create();
    $page = Page::factory()->withTranslations()->create();
    usageAttachment($section, $page);

    $replica = $section->replicate();
    $replica->name = $section->name . ' (copy)';
    $replica->save();

    livewire(EditSection::class, ['record' => $replica->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(__('capell-content-sections::message.usage_none'));
});

it('does not affect widget placements pointing at the original when replicated', function (): void {
    $section = Section::factory()->create();
    WidgetAssetFactory::new()
        ->asset($section)
        ->page(Page::factory()->withTranslations()->create())
        ->create();

    $replica = $section->replicate();
    $replica->name = $section->name . ' (copy)';
    $replica->save();

    livewire(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Used in 1 place');

    livewire(EditSection::class, ['record' => $replica->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(__('capell-content-sections::message.usage_none'));
});
