<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Capell\ContentSections\Filament\Resources\Sections\Pages\EditSection;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Site;
use Capell\PublishingStudio\Actions\InstallWorkspaceRolesAction;
use Capell\PublishingStudio\Enums\WorkspaceStatusEnum;
use Capell\PublishingStudio\Models\PublishingRevision;
use Capell\PublishingStudio\Models\Workspace;
use Capell\PublishingStudio\WorkspaceContext;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(CreatesAdminUser::class);

function contentSectionPermission(string $affix): string
{
    $permissions = Utils::getConfig()->permissions;

    return FilamentShield::defaultPermissionKeyBuilder(
        affix: $affix,
        separator: $permissions->separator,
        subject: 'Section',
        case: $permissions->case,
    );
}

it('shows draft controls for live sections without revision history', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => null,
    ]);

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertActionVisible('saveAsDraft')
        ->assertActionVisible('unpublish')
        ->assertActionHidden('publish')
        ->assertActionHidden('publishingRevisions');
});

it('labels the primary save action as save and publish', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();
    $component = Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->instance();
    $method = new ReflectionMethod($component, 'getSaveFormAction');

    /** @var Action $saveAction */
    $saveAction = $method->invoke($component);
    $label = $saveAction->getLabel();

    expect($saveAction)->toBeInstanceOf(Action::class)
        ->and($label instanceof Htmlable ? $label->toHtml() : $label)
        ->toBe(__('capell-content-sections::button.save_and_publish'));
});

it('unpublishes live sections immediately', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => null,
    ]);

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->callAction('unpublish')
        ->assertHasNoActionErrors();

    expect($section->fresh()->visible_until)->not->toBeNull()
        ->and($section->fresh()->visible_until?->isPast())->toBeTrue();
});

it('hides unpublish for draft and expired sections', function (): void {
    test()->actingAsAdmin();

    $workspace = Workspace::factory()->create();
    $draft = Section::factory()->create(['workspace_id' => $workspace->id]);
    $expired = Section::factory()->create([
        'visible_from' => now()->subWeek(),
        'visible_until' => now()->subDay(),
    ]);

    WorkspaceContext::runWith($workspace, function () use ($draft): void {
        Livewire::test(EditSection::class, ['record' => $draft->getRouteKey()])
            ->assertActionHidden('unpublish');
    });

    Livewire::test(EditSection::class, ['record' => $expired->getRouteKey()])
        ->assertActionHidden('unpublish');
});

it('shows cancel scheduled unpublish only for sections with a future visible until date', function (): void {
    test()->actingAsAdmin();

    $scheduled = Section::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addWeek(),
    ]);
    $live = Section::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => null,
    ]);

    Livewire::test(EditSection::class, ['record' => $scheduled->getRouteKey()])
        ->assertActionVisible('cancelScheduledUnpublish');

    Livewire::test(EditSection::class, ['record' => $live->getRouteKey()])
        ->assertActionHidden('cancelScheduledUnpublish');
});

it('cancels a scheduled section unpublish', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addWeek(),
    ]);

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->callAction('cancelScheduledUnpublish')
        ->assertHasNoActionErrors();

    expect($section->fresh()->visible_until)->toBeNull();
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

it('hides workspace publish controls from editors without publish permission', function (): void {
    Permission::findOrCreate(contentSectionPermission('view'));
    Permission::findOrCreate(contentSectionPermission('update'));
    Permission::findOrCreate(InstallWorkspaceRolesAction::PERMISSION_PUBLISH);

    $editor = test()->createUserWithPermission([
        contentSectionPermission('view'),
        contentSectionPermission('update'),
    ]);
    $site = Site::factory()->create();
    $role = Role::findOrCreate('section-site-editor', 'web');
    $editor->assignRoleForSite($site, $role);
    test()->actingAs($editor);

    $workspace = Workspace::factory()->create(['status' => WorkspaceStatusEnum::Open]);
    $section = Section::factory()
        ->site($site)
        ->create(['workspace_id' => $workspace->id]);

    WorkspaceContext::runWith($workspace, function () use ($section): void {
        Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
            ->assertActionHidden('publish');
    });
});
