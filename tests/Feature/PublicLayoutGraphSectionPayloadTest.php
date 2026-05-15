<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\EnsureSectionBlueprintForKeyAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Actions\BuildPublicLayoutGraphAction;
use Capell\LayoutBuilder\Models\Element;
use Capell\LayoutBuilder\Models\ElementAsset;

it('contributes section assets to public layout element payloads', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->id]);
    $blueprint = EnsureSectionBlueprintForKeyAction::run('hero');
    $section = Section::factory()
        ->site($site)
        ->blueprint($blueprint)
        ->withTranslations($language, [
            'title' => 'Hero Copy',
            'content' => '<p>Hero summary</p>',
        ])
        ->create([
            'name' => 'Hero section',
            'meta' => ['alignment' => 'center'],
            'visible_until' => now()->addDay(),
        ]);

    $element = Element::factory()->create(['key' => 'hero-element']);
    $layout = Layout::factory()->site($site)->create([
        'containers' => [
            'main' => ['elements' => [['element_key' => $element->key, 'occurrence' => 1]]],
        ],
    ]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create();

    ElementAsset::factory()
        ->element($element)
        ->asset($section)
        ->create([
            'meta' => ['alignment' => 'start'],
            'order' => 1,
        ]);

    $graph = BuildPublicLayoutGraphAction::run($layout, $page, $language, includeHtml: true);
    $elementData = $graph->containers[0]->elements[0];

    expect($elementData->data['sections'][0])
        ->toMatchArray([
            'id' => $section->getKey(),
            'key' => 'hero',
            'component' => 'capell-content-sections::section.blocks.hero',
            'title' => 'Hero Copy',
            'summary' => '<p>Hero summary</p>',
            'meta' => ['alignment' => 'start'],
        ])
        ->and($elementData->html)->toContain('section-hero')
        ->and($elementData->html)->toContain('Hero Copy')
        ->and($elementData->html)->toContain('Hero summary');
});

it('does not expose pending or expired section assets in public layout element payloads', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->id]);
    $blueprint = EnsureSectionBlueprintForKeyAction::run('hero');
    $pendingSection = Section::factory()
        ->site($site)
        ->blueprint($blueprint)
        ->withTranslations($language, [
            'title' => 'Pending Copy',
            'content' => '<p>Pending summary</p>',
        ])
        ->create([
            'name' => 'Pending section',
            'visible_from' => now()->addDay(),
        ]);
    $expiredSection = Section::factory()
        ->site($site)
        ->blueprint($blueprint)
        ->withTranslations($language, [
            'title' => 'Expired Copy',
            'content' => '<p>Expired summary</p>',
        ])
        ->create([
            'name' => 'Expired section',
            'visible_until' => now()->subDay(),
        ]);

    $element = Element::factory()->create(['key' => 'hero-element']);
    $layout = Layout::factory()->site($site)->create([
        'containers' => [
            'main' => ['elements' => [['element_key' => $element->key, 'occurrence' => 1]]],
        ],
    ]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create();

    ElementAsset::factory()->element($element)->asset($pendingSection)->create(['order' => 1]);
    ElementAsset::factory()->element($element)->asset($expiredSection)->create(['order' => 2]);

    $graph = BuildPublicLayoutGraphAction::run($layout, $page, $language, includeHtml: true);
    $elementData = $graph->containers[0]->elements[0];

    expect($elementData->data)->not->toHaveKey('sections')
        ->and($elementData->html)->toBeNull();
});
