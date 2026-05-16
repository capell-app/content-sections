<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\BuildSectionAssetRenderDataAction;
use Capell\ContentSections\Actions\EnsureSectionBlueprintForKeyAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Database\Factories\UrlFactory;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\SiteDomain;
use Illuminate\Support\Facades\DB;

it('spreads attributes onto dynamic section components', function (): void {
    $asset = new class
    {
        public object $translation;

        /** @var array<string, mixed> */
        public array $meta = [];

        public ?object $linkedPage = null;

        public function __construct()
        {
            $this->translation = (object) [
                'label' => 'Alice Johnson',
                'summary' => 'Leads the product team.',
            ];
        }

        public function getMeta(string $key, mixed $default = null): mixed
        {
            return [
                'color' => 'blue',
                'icon' => null,
            ][$key] ?? $default;
        }
    };

    $this->blade(
        <<<'BLADE'
        <x-capell-content-sections::section.asset
            :asset="$asset"
            component-item="section.block"
            :loop="$loop"
            :class="$class"
        />
        BLADE,
        [
            'asset' => $asset,
            'class' => ['element-block-item'],
            'loop' => (object) ['index' => 0],
        ],
    )
        ->assertSee('Alice Johnson')
        ->assertSee('section-asset')
        ->assertSee('element-block-item');
});

it('builds section asset render data from loaded eloquent relations', function (): void {
    $language = Language::factory()->create();
    $siteDomain = SiteDomain::factory()->default()->language($language)->create();
    $site = $siteDomain->site;
    $blueprint = EnsureSectionBlueprintForKeyAction::run('hero');
    $linkedPage = Page::factory()->site($site)->withTranslations($language)->create();
    $pageUrl = UrlFactory::new()
        ->site($site)
        ->language($language)
        ->page($linkedPage)
        ->create(['url' => '/linked-page']);
    $linkedPage->setRelation('pageUrl', $pageUrl);
    $section = Section::factory()
        ->site($site)
        ->blueprint($blueprint)
        ->withTranslations($language, [
            'title' => 'Loaded section',
            'meta' => ['summary' => 'Loaded summary'],
        ])
        ->create([
            'meta' => [
                'color' => 'green',
                'icon' => 'heroicon-o-star',
                'linked_pageable_id' => $linkedPage->getKey(),
                'linked_pageable_type' => $linkedPage->getMorphClass(),
            ],
        ]);
    $section->load('translation');
    $section->setRelation('linkedPage', $linkedPage);

    $data = BuildSectionAssetRenderDataAction::run(
        asset: $section,
        componentItem: 'section.block',
        withLinkText: true,
        withSummary: true,
        withUrl: true,
    );

    expect($data->title)->toBe('Loaded section')
        ->and($data->summary)->toBe('Loaded summary')
        ->and($data->color)->toBe('green')
        ->and($data->icon)->toBe('heroicon-o-star')
        ->and($data->url)->toBe($pageUrl->full_url);
});

it('does not lazy-load linked page URLs while building section asset render data', function (): void {
    $language = Language::factory()->create();
    $siteDomain = SiteDomain::factory()->default()->language($language)->create();
    $site = $siteDomain->site;
    $blueprint = EnsureSectionBlueprintForKeyAction::run('hero');
    $linkedPage = Page::factory()->site($site)->withTranslations($language)->create();
    $section = Section::factory()
        ->site($site)
        ->blueprint($blueprint)
        ->withTranslations($language, [
            'title' => 'Persisted section',
            'meta' => ['summary' => 'Persisted summary'],
        ])
        ->create([
            'meta' => [
                'linked_pageable_id' => $linkedPage->getKey(),
                'linked_pageable_type' => $linkedPage->getMorphClass(),
            ],
        ]);
    $section->load('translation');

    DB::enableQueryLog();

    $data = BuildSectionAssetRenderDataAction::run(
        asset: $section,
        componentItem: 'section.block',
        withLinkText: true,
        withSummary: true,
        withUrl: true,
    );

    expect($data->url)->toBeNull()
        ->and(DB::getQueryLog())->toBe([]);

    DB::disableQueryLog();
});
