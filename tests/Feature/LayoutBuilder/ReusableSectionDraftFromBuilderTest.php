<?php

declare(strict_types=1);

use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Layout;
use Capell\LayoutBuilder\Livewire\Filament\LayoutBuilder;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Livewire\Livewire;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    test()->actingAsAdmin();
});

it('saves reusable section asset edits into a section draft workspace from a live builder context', function (): void {
    $block = Widget::factory()->create(['key' => 'featured', 'name' => 'Featured']);
    /** @var Section $section */
    $section = Section::factory()->create(['name' => 'Live reusable section']);
    $blockAsset = WidgetAsset::factory()
        ->block($block)
        ->asset($section)
        ->occurrence(1)
        ->create(['order' => 1]);

    $layout = Layout::factory()->create(['containers' => [
        'main' => ['widgets' => [
            ['widget_key' => $block->key, 'occurrence' => 1],
        ]],
    ]]);

    Livewire::test(LayoutBuilder::class, ['layout' => $layout])
        ->callAction('editBlockAsset', data: [
            'asset' => [
                'name' => 'Draft reusable section',
            ],
        ], arguments: [
            'containerKey' => 'main',
            'blockIndex' => 0,
            'index' => 0,
            'type' => 'section',
        ])
        ->assertHasNoActionErrors();

    $draft = Section::query()
        ->withoutGlobalScopes()
        ->where('uuid', $section->uuid)
        ->where('workspace_id', '>', 0)
        ->firstOrFail();

    $live = Section::query()
        ->withoutGlobalScopes()
        ->findOrFail((int) $section->getKey());

    expect($live->name)->toBe('Live reusable section')
        ->and((int) $blockAsset->fresh()->asset_id)->toBe($section->getKey())
        ->and($draft->name)->toBe('Draft reusable section')
        ->and($draft->uuid)->toBe($section->uuid)
        ->and($live->shadowed_by_workspace_id)->toBe($draft->workspace_id);
});

it('creates reusable section assets from the builder as draft records', function (): void {
    $block = Widget::factory()->create(['key' => 'featured', 'name' => 'Featured']);
    $layout = Layout::factory()->create(['containers' => [
        'main' => ['widgets' => [
            ['widget_key' => $block->key, 'occurrence' => 1],
        ]],
    ]]);

    Livewire::test(LayoutBuilder::class, ['layout' => $layout])
        ->callAction('addAsset', data: [
            'asset' => [
                'name' => 'Created reusable section',
            ],
        ], arguments: [
            'containerKey' => 'main',
            'blockIndex' => 0,
            'type' => 'section',
        ])
        ->assertHasNoActionErrors();

    $draft = Section::query()
        ->withoutGlobalScopes()
        ->where('name', 'Created reusable section')
        ->where('workspace_id', '>', 0)
        ->firstOrFail();

    expect($draft->uuid)->not->toBeEmpty()
        ->and(Section::query()->withoutGlobalScopes()->where('workspace_id', 0)->where('uuid', $draft->uuid)->exists())->toBeFalse();
});
