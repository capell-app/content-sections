<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\BuildSectionUsageSummaryAction;
use Capell\ContentSections\Enums\AssetEnum as SectionAssetEnum;
use Capell\ContentSections\Models\Section;
use Capell\Core\Enums\AssetEnum;
use Capell\Core\Models\AssetAttachment;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Database\Factories\WidgetAssetFactory;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(CreatesAdminUser::class)
    ->group('section');

/**
 * Assigns the actor to the given sites via a direct `model_has_roles` insert.
 * `HasSitePermissions::assignRoleForSite()` relies on Spatie teams, and this
 * Testbench environment runs the published default config with teams
 * disabled, so `assignRole()` never writes a `team_id`. Insert the pivot row
 * directly, matching the same workaround other packages in this monorepo use
 * (e.g. html-cache's `maintenanceCacheSiteManager()`).
 *
 * @param  list<Site>  $sites
 */
function usageSiteScopedActor(array $sites): User
{
    $user = User::factory()->create();
    $role = Role::findOrCreate('content-sections-usage-site-actor', 'web');

    foreach ($sites as $site) {
        DB::table('model_has_roles')->insert([
            'role_id' => $role->getKey(),
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
            'team_id' => $site->getKey(),
        ]);
    }

    resolve(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function attachmentUsage(Section $section, Model $related): AssetAttachment
{
    return AssetAttachment::factory([
        'asset_type' => SectionAssetEnum::Section->value,
        'asset_id' => $section->getKey(),
    ])->related($related)->create();
}

it('returns an empty summary when nobody is authenticated', function (): void {
    $section = Section::factory()->create();

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->totalCount())->toBe(0)
        ->and($summary->isUsed())->toBeFalse()
        ->and($summary->isAuthoritative)->toBeTrue();
});

it('reports zero usage for an unused section', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->attachmentCount)->toBe(0)
        ->and($summary->widgetCount)->toBe(0)
        ->and($summary->isUsed())->toBeFalse()
        ->and($summary->destinations)->toBe([]);
});

it('counts a single generic attachment as usage', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();
    $page = Page::factory()->withTranslations()->create(['name' => 'Landing page']);
    attachmentUsage($section, $page);

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->attachmentCount)->toBe(1)
        ->and($summary->widgetCount)->toBe(0)
        ->and($summary->totalCount())->toBe(1)
        ->and($summary->destinations)->toHaveCount(1)
        ->and($summary->destinations[0]->kind)->toBe('attachment')
        ->and($summary->destinations[0]->title)->toBe('Landing page');
});

it('counts many generic attachments and widget placements together', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();

    Page::factory()->count(3)->withTranslations()->create()->each(
        fn (Page $page) => attachmentUsage($section, $page),
    );

    WidgetAssetFactory::new()
        ->asset($section)
        ->page(Page::factory()->withTranslations()->create())
        ->container('hero')
        ->count(2)
        ->create();

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->attachmentCount)->toBe(3)
        ->and($summary->widgetCount)->toBe(2)
        ->and($summary->totalCount())->toBe(5)
        ->and($summary->destinations)->toHaveCount(5);
});

it('reconciles the total count against a capped destination preview', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();

    Page::factory()->count(9)->withTranslations()->create()->each(
        fn (Page $page) => attachmentUsage($section, $page),
    );

    WidgetAssetFactory::new()
        ->asset($section)
        ->page(Page::factory()->withTranslations()->create())
        ->count(9)
        ->create();

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->attachmentCount)->toBe(9)
        ->and($summary->widgetCount)->toBe(9)
        ->and($summary->totalCount())->toBe(18)
        ->and(count($summary->destinations))->toBeLessThanOrEqual(12)
        ->and($summary->totalCount())->toBeGreaterThan(count($summary->destinations));
});

it('excludes widget placements without a resolvable page or layout owner', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();

    WidgetAssetFactory::new()
        ->asset($section)
        ->create(['pageable_id' => null, 'pageable_type' => null]);

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->widgetCount)->toBe(0)
        ->and($summary->isUsed())->toBeFalse();
});

it('resolves layout placements with container and site context', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();
    $site = Site::factory()->create(['name' => 'Marketing site']);
    $layout = Layout::factory()->create(['name' => 'Footer layout', 'site_id' => $site->id]);

    WidgetAssetFactory::new()
        ->asset($section)
        ->state([
            'pageable_id' => $layout->id,
            'pageable_type' => $layout->getMorphClass(),
            'container' => 'footer',
            'occurrence' => 2,
        ])
        ->create();

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->widgetCount)->toBe(1)
        ->and($summary->destinations)->toHaveCount(1)
        ->and($summary->destinations[0]->kind)->toBe('widget')
        ->and($summary->destinations[0]->type)->toBe('Layout')
        ->and($summary->destinations[0]->title)->toBe('Footer layout')
        ->and($summary->destinations[0]->context)->toBe('footer')
        ->and($summary->destinations[0]->site)->toBe('Marketing site');
});

it('counts a soft-deleted related record as usage without exposing its identity', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();
    $page = Page::factory()->withTranslations()->create();
    attachmentUsage($section, $page);
    $page->delete();

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->attachmentCount)->toBe(1)
        ->and($summary->destinations)->toBe([]);
});

it('counts an attachment whose related record no longer exists at all', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();

    AssetAttachment::factory([
        'asset_type' => SectionAssetEnum::Section->value,
        'asset_id' => (string) $section->getKey(),
        'related_type' => AssetEnum::Page->value,
        'related_id' => '999999',
    ])->create();

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->attachmentCount)->toBe(1)
        ->and($summary->destinations)->toBe([]);
});

it('limits a site-scoped actor to usage on their assigned sites and marks the summary non-authoritative', function (): void {
    $siteA = Site::factory()->create();
    $siteB = Site::factory()->create();
    $actor = usageSiteScopedActor([$siteA]);

    $section = Section::factory()->create();
    $visiblePage = Page::factory()->withTranslations()->create(['site_id' => $siteA->id]);
    $hiddenPage = Page::factory()->withTranslations()->create(['site_id' => $siteB->id]);
    attachmentUsage($section, $visiblePage);
    attachmentUsage($section, $hiddenPage);

    $visibleLayout = Layout::factory()->create(['site_id' => $siteA->id]);
    $hiddenLayout = Layout::factory()->create(['site_id' => $siteB->id]);
    WidgetAssetFactory::new()->asset($section)->state([
        'pageable_id' => $visibleLayout->id,
        'pageable_type' => $visibleLayout->getMorphClass(),
    ])->create();
    WidgetAssetFactory::new()->asset($section)->state([
        'pageable_id' => $hiddenLayout->id,
        'pageable_type' => $hiddenLayout->getMorphClass(),
    ])->create();

    test()->actingAs($actor);

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->isAuthoritative)->toBeFalse()
        ->and($summary->attachmentCount)->toBe(1)
        ->and($summary->widgetCount)->toBe(1)
        ->and(collect($summary->destinations)->pluck('title'))->not->toContain($hiddenPage->name)
        ->and(collect($summary->destinations)->pluck('title'))->not->toContain($hiddenLayout->name);
});

it('keeps a global actor authoritative and able to see every site', function (): void {
    test()->actingAsAdmin();

    $section = Section::factory()->create();
    $siteA = Site::factory()->create();
    $siteB = Site::factory()->create();
    attachmentUsage($section, Page::factory()->withTranslations()->create(['site_id' => $siteA->id]));
    attachmentUsage($section, Page::factory()->withTranslations()->create(['site_id' => $siteB->id]));

    $summary = BuildSectionUsageSummaryAction::run($section);

    expect($summary->isAuthoritative)->toBeTrue()
        ->and($summary->attachmentCount)->toBe(2);
});

it('keeps query counts bounded regardless of how much usage exists', function (): void {
    test()->actingAsAdmin();

    $small = Section::factory()->create();
    Page::factory()->count(2)->withTranslations()->create()->each(
        fn (Page $page) => attachmentUsage($small, $page),
    );

    $large = Section::factory()->create();
    Page::factory()->count(40)->withTranslations()->create()->each(
        fn (Page $page) => attachmentUsage($large, $page),
    );
    WidgetAssetFactory::new()->asset($large)->page(Page::factory()->withTranslations()->create())->count(20)->create();

    $countQueries = function (Section $section): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        BuildSectionUsageSummaryAction::run($section);
        $count = count(DB::getQueryLog());
        DB::flushQueryLog();

        return $count;
    };

    $smallQueryCount = $countQueries($small);
    $largeQueryCount = $countQueries($large);

    expect($largeQueryCount)->toBeLessThanOrEqual($smallQueryCount + 5);
});
