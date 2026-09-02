<?php

declare(strict_types=1);

use Capell\ContentSections\Enums\AssetEnum as SectionAssetEnum;
use Capell\ContentSections\Filament\Resources\Sections\Pages\ListSections;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\AssetAttachment;
use Capell\Core\Models\Page;
use Capell\LayoutBuilder\Database\Factories\WidgetAssetFactory;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('section');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

function listUsageAttachment(Section $section, Page $page): AssetAttachment
{
    return AssetAttachment::factory([
        'asset_type' => SectionAssetEnum::Section->value,
        'asset_id' => $section->getKey(),
    ])->related($page)->create();
}

it('shows an unused section as not used in the index', function (): void {
    $section = Section::factory()->create();

    livewire(ListSections::class)
        ->assertSuccessful()
        ->assertTableColumnStateSet('used_in', 0, $section);
});

it('shows the combined attachment and widget usage count in the index', function (): void {
    $section = Section::factory()->create();
    listUsageAttachment($section, Page::factory()->withTranslations()->create());
    listUsageAttachment($section, Page::factory()->withTranslations()->create());
    WidgetAssetFactory::new()
        ->asset($section)
        ->page(Page::factory()->withTranslations()->create())
        ->create();

    livewire(ListSections::class)
        ->assertSuccessful()
        ->assertTableColumnStateSet('used_in', 3, $section);
});

it('still labels the outgoing assets column as content the section contains', function (): void {
    livewire(ListSections::class)
        ->assertSuccessful()
        ->assertSee(__('capell-content-sections::table.assets'));
});

it('keeps the used-in column to a single bounded query regardless of row or usage count', function (): void {
    Section::factory()->count(3)->create()->each(function (Section $section): void {
        Page::factory()->count(5)->withTranslations()->create()->each(
            fn (Page $page) => listUsageAttachment($section, $page),
        );
    });

    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries): void {
        $queries[] = $event->sql;
    });

    livewire(ListSections::class)->assertSuccessful();

    // Ignore schema introspection. WorkspaceContextScope verifies once per
    // process that a table carries both workspace columns, and that probe
    // (pragma_table_xinfo / sqlite_master / information_schema) names the
    // table as a string literal, so it matches the filter below without being
    // a usage lookup. It is a fixed cost, not per-row: proven by running this
    // test with 7 sections instead of 3 and still seeing exactly two probes.
    // Filtering them out keeps the real assertion at exactly one query.
    $usageQueries = collect($queries)
        ->reject(fn (string $sql): bool => str_contains($sql, 'pragma_')
            || str_contains($sql, 'sqlite_master')
            || str_contains($sql, 'information_schema'))
        ->filter(
            fn (string $sql): bool => str_contains($sql, 'asset_attachments') || str_contains($sql, 'widget_assets'),
        );

    // Both usage counts are correlated subqueries embedded in the single list
    // query, so exactly one query should reference them, regardless of how
    // many rows or how much usage exists — never one query per row.
    expect($usageQueries)->toHaveCount(1)
        ->and($usageQueries->first())->toMatch('/from (?:`|")sections(?:`|")/')
        ->and($usageQueries->first())->toContain('asset_attachments')
        ->and($usageQueries->first())->toContain('widget_assets');
});
