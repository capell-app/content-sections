<?php

declare(strict_types=1);

use Capell\ContentSections\Filament\Resources\Sections\Pages\EditSection;
use Capell\ContentSections\Filament\Resources\Sections\Pages\ListSections;
use Capell\ContentSections\Filament\Resources\Sections\RelationManagers\SectionAssetsRelationManager;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\AssetAttachment;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\Mechanisms\ExtendBlade\ExtendBlade;

uses(CreatesAdminUser::class);

it('preserves legacy delete consequence translations', function (string $translation, string $key): void {
    expect($translation)->not->toBeEmpty()->not->toBe($key);
})->with([
    'delete consequence' => [fn (): string => __('capell-content-sections::message.usage_delete_consequence'), 'capell-content-sections::message.usage_delete_consequence'],
    'force delete consequence' => [fn (): string => __('capell-content-sections::message.usage_force_delete_consequence'), 'capell-content-sections::message.usage_force_delete_consequence'],
]);

/**
 * @param  class-string<Component>  $page
 * @param  Closure(): Model  $makeRecord
 */
it('renders translated delete consequences in the real admin surface', function (string $page, Closure $makeRecord, string $mode, string $actionName, string $translation, bool $force): void {
    test()->actingAsAdmin();
    $heading = __($translation . '.heading');
    $description = __($translation . '.description');

    expect($heading)->toBeString()->not->toBeEmpty()->not->toBe($translation . '.heading')
        ->and($description)->toBeString()->not->toBeEmpty()->not->toBe($translation . '.description');

    $record = $makeRecord();

    $parameters = $mode === 'page' ? ['record' => $record->getRouteKey()] : [];
    if ($page === SectionAssetsRelationManager::class) {
        $owner = $record->getRelationValue('related');
        throw_unless($owner instanceof Section, LogicException::class);
        $parameters = ['ownerRecord' => $owner, 'pageClass' => EditSection::class];
    }

    $component = Livewire::test($page, $parameters)->assertSuccessful();
    if ($page === SectionAssetsRelationManager::class) {
        $component->call('loadTable')->assertCanSeeTableRecords([$record]);
    }

    if ($force) {
        $record->delete();
        $surface = $component->instance();
        if ($mode === 'page') {
            throw_unless($surface instanceof HasActions, LogicException::class);
            $action = $surface->getAction($actionName);
        } else {
            throw_unless($surface instanceof HasTable, LogicException::class);
            $action = $surface->getTable()->getBulkAction($actionName);
        }

        throw_unless($action instanceof Action, LogicException::class);
        // Render the real action in its page context without rehydrating a trashed route record.
        $blade = resolve(ExtendBlade::class);
        $blade->startLivewireRendering($surface);
        try {
            $html = $action->record($record)->toModalHtmlable()->toHtml();
        } finally {
            $blade->endLivewireRendering();
        }

        expect($html)->toContain(e($heading), e($description));

        return;
    }

    if ($mode === 'page') {
        $component->mountAction($actionName);
    } else {
        if ($mode === 'bulk') {
            $component->mountTableBulkAction($actionName, [$record->getKey()]);
        } else {
            $component->mountTableAction($actionName, $record);
        }
    }

    $component->assertMountedActionModalSee($heading)->assertMountedActionModalSee($description);
})->with([
    'EditSection DeleteAction' => [EditSection::class, fn (): Model => Section::factory()->create(), 'page', 'delete', 'capell-content-sections::section.delete_modal', false],
    'EditSection ForceDeleteAction' => [EditSection::class, fn (): Model => Section::factory()->create(), 'page', 'forceDelete', 'capell-content-sections::section.force_delete_modal', true],
    'SectionAssetsRelationManager DeleteBulkAction' => [SectionAssetsRelationManager::class, fn (): Model => AssetAttachment::factory()->related(Section::factory()->create())->create(), 'bulk', 'delete', 'capell-content-sections::section_asset.delete_modal', false],
    'SectionsTable DeleteAction' => [ListSections::class, fn (): Model => Section::factory()->create(), 'table', 'delete', 'capell-content-sections::section.delete_modal', false],
    'SectionsTable DeleteBulkAction' => [ListSections::class, fn (): Model => Section::factory()->create(), 'bulk', 'delete', 'capell-content-sections::section.delete_bulk_modal', false],
    'SectionsTable ForceDeleteBulkAction' => [ListSections::class, fn (): Model => Section::factory()->create(), 'bulk', 'forceDelete', 'capell-content-sections::section.force_delete_bulk_modal', true],
]);
