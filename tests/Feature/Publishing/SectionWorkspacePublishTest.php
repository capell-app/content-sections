<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\CloneSectionIntoWorkspaceAction;
use Capell\ContentSections\Actions\FinalizeSectionPublishAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\AssetAttachment;
use Capell\Core\Models\Media;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\PublishingStudio\Actions\ListPublishingRevisionsAction;
use Capell\PublishingStudio\Actions\SaveRecordDraftAction;
use Capell\PublishingStudio\Enums\WorkspaceStatusEnum;
use Capell\PublishingStudio\Models\Workspace;
use Capell\PublishingStudio\Publisher;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Str;

it('clones sections into workspaces through the package action', function (): void {
    $uuid = (string) Str::uuid();
    $live = Section::factory()->create([
        'uuid' => $uuid,
        'workspace_id' => 0,
        'name' => 'Live reusable section',
    ]);
    $workspace = Workspace::factory()->create();

    Translation::factory()->translatable($live)->create([
        'title' => 'Translated section',
        'content' => '<p>Translated copy</p>',
    ]);
    AssetAttachment::factory()->related($live)->create();
    Media::factory()
        ->model($live)
        ->collection(MediaCollectionEnum::Image)
        ->create();

    $draft = CloneSectionIntoWorkspaceAction::run($live, $workspace);

    expect($draft)->toBeInstanceOf(Section::class)
        ->and($draft->getKey())->not->toBe($live->getKey())
        ->and($draft->workspace_id)->toBe($workspace->id)
        ->and($draft->shadowed_by_workspace_id)->toBe(0)
        ->and($draft->uuid)->toBe($uuid)
        ->and($draft->translations()->count())->toBe(1)
        ->and($draft->assets()->count())->toBe(1)
        ->and($draft->media()->where('collection_name', MediaCollectionEnum::Image->value)->count())->toBe(1);
});

it('repoints layout builder section usages to the published section draft row', function (): void {
    $uuid = (string) Str::uuid();
    $live = Section::factory()->create([
        'uuid' => $uuid,
        'workspace_id' => 0,
        'name' => 'Live reusable section',
    ]);
    $draft = Section::factory()->create([
        'uuid' => $uuid,
        'workspace_id' => 123,
        'name' => 'Draft reusable section',
    ]);
    $widget = Widget::factory()->create();

    $usage = WidgetAsset::factory()
        ->widget($widget)
        ->asset($live)
        ->create();

    FinalizeSectionPublishAction::run($draft);

    expect((int) $usage->fresh()->asset_id)->toBe($draft->getKey());
});

it('publishes section drafts through publisher with revisions media and site wide builder usages preserved', function (): void {
    config()->set('capell.publishing-studio.release_windows.enabled', false);

    $user = User::factory()->create();
    $uuid = (string) Str::uuid();
    $live = Section::factory()->create([
        'uuid' => $uuid,
        'workspace_id' => 0,
        'name' => 'Live reusable section',
    ]);
    $firstWidget = Widget::factory()->create();
    $secondWidget = Widget::factory()->create();
    $firstUsage = WidgetAsset::factory()
        ->widget($firstWidget)
        ->asset($live)
        ->create();
    $secondUsage = WidgetAsset::factory()
        ->widget($secondWidget)
        ->asset($live)
        ->create();

    Media::factory()
        ->model($live)
        ->collection(MediaCollectionEnum::Image)
        ->create();

    expect(ListPublishingRevisionsAction::run($live))->toBeEmpty();

    $result = SaveRecordDraftAction::run($live, ['name' => 'Published reusable section'], $user);

    expect($result->record->getAttribute('uuid'))->toBe($uuid)
        ->and($result->record->media()->where('collection_name', MediaCollectionEnum::Image->value)->count())->toBe(1)
        ->and($live->fresh()->name)->toBe('Live reusable section');

    $result->workspace->update(['status' => WorkspaceStatusEnum::Approved]);

    resolve(Publisher::class)->publish($result->workspace->fresh(), $user, bypassWindow: true);

    $published = Section::query()
        ->withoutGlobalScopes()
        ->where('uuid', $uuid)
        ->where('workspace_id', 0)
        ->whereNull('deleted_at')
        ->firstOrFail();

    expect($published->getKey())->toBe($result->record->getKey())
        ->and($published->name)->toBe('Published reusable section')
        ->and($published->media()->where('collection_name', MediaCollectionEnum::Image->value)->count())->toBe(1)
        ->and((int) $firstUsage->fresh()->asset_id)->toBe($published->getKey())
        ->and((int) $secondUsage->fresh()->asset_id)->toBe($published->getKey())
        ->and(ListPublishingRevisionsAction::run($published))->toHaveCount(1);
});
