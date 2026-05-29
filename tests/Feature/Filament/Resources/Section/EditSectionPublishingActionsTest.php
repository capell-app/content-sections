<?php

declare(strict_types=1);

use Capell\ContentSections\Filament\Resources\Sections\Pages\EditSection;
use Capell\ContentSections\Models\Section;
use Capell\PublishingStudio\Models\PublishingRevision;
use Capell\PublishingStudio\Models\Workspace;
use Capell\PublishingStudio\WorkspaceContext;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Livewire\Livewire;

uses(CreatesAdminUser::class);

it('shows draft controls for live sections without revision history', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertActionVisible('saveAsDraft')
        ->assertActionHidden('publish')
        ->assertActionHidden('publishingRevisions');
});

it('saves a live section as a draft without mutating the live section', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create(['name' => 'Live section']);

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->fillForm(['name' => 'Draft section'])
        ->callAction('saveAsDraft')
        ->assertHasNoFormErrors();

    $draft = Section::query()
        ->withoutGlobalScopes()
        ->where('uuid', $section->uuid)
        ->where('workspace_id', '>', 0)
        ->firstOrFail();

    expect($section->fresh()->name)->toBe('Live section')
        ->and($section->fresh()->shadowed_by_workspace_id)->toBe($draft->workspace_id)
        ->and($draft->name)->toBe('Draft section')
        ->and($draft->uuid)->toBe($section->uuid);
});

it('does not create revisions when opening the revision history action', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertActionHidden('publishingRevisions');

    expect(PublishingRevision::query()->count())->toBe(0);
});

it('shows publish controls for draft sections', function (): void {
    test()->actingAsAdmin();

    $workspace = Workspace::factory()->create();
    $section = Section::factory()->create(['workspace_id' => $workspace->id]);

    WorkspaceContext::runWith($workspace, function () use ($section): void {
        Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
            ->assertActionHidden('saveAsDraft')
            ->assertActionVisible('publish')
            ->assertActionDisabled('publish');
    });
});
