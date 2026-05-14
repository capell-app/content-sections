<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\EnsureSectionTypeForKeyAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\LayoutBuilder\Actions\BuildPublicLayoutGraphAction;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Widget;
use Capell\Core\Models\WidgetAsset;

it('contributes section assets to public layout widget payloads', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->id]);
    $type = EnsureSectionTypeForKeyAction::run('hero');
    $section = Section::factory()
        ->site($site)
        ->type($type)
        ->withTranslations($language, [
            'title' => 'Hero Copy',
            'content' => '<p>Hero summary</p>',
        ])
        ->create([
            'name' => 'Hero section',
            'meta' => ['alignment' => 'center'],
        ]);

    $widget = Widget::factory()->create(['key' => 'hero-widget']);
    $layout = Layout::factory()->site($site)->create([
        'widgets' => [$widget->key],
        'containers' => [
            'main' => ['widgets' => [['widget_key' => $widget->key, 'occurrence' => 1]]],
        ],
    ]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create();

    WidgetAsset::factory()
        ->widget($widget)
        ->asset($section)
        ->create([
            'meta' => ['alignment' => 'start'],
            'order' => 1,
        ]);

    $graph = BuildPublicLayoutGraphAction::run($layout, $page, $language, includeHtml: true);
    $widgetData = $graph->containers[0]->widgets[0];

    expect($widgetData->data['sections'][0])
        ->toMatchArray([
            'id' => $section->getKey(),
            'key' => 'hero',
            'component' => 'capell-content-sections::section.blocks.hero',
            'title' => 'Hero Copy',
            'summary' => '<p>Hero summary</p>',
            'meta' => ['alignment' => 'start'],
        ])
        ->and($widgetData->html)->toContain('section-hero')
        ->and($widgetData->html)->toContain('Hero Copy')
        ->and($widgetData->html)->toContain('Hero summary');
});
