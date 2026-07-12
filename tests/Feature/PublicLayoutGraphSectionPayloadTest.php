<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\EnsureSectionBlueprintForKeyAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Actions\BuildPublicLayoutGraphAction;
use Capell\LayoutBuilder\Data\PublicLayoutWidgetData;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Database\Eloquent\Model;

it('contributes section assets to public layout widget payloads', function (): void {
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

    $widget = Widget::factory()->create(['key' => 'hero-widget']);
    $layout = Layout::factory()->site($site)->create([
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
    $sectionPayload = publicLayoutGraphSectionPayload($widgetData);

    expect($sectionPayload)
        ->toMatchArray([
            'id' => $section->getKey(),
            'key' => 'hero',
            'component' => 'capell-block-library::blocks.catalog.hero',
            'title' => 'Hero Copy',
            'summary' => '<p>Hero summary</p>',
            'meta' => ['alignment' => 'start'],
        ]);
});

it('contributes section assets without public-render lazy loading', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->id]);
    $blueprint = EnsureSectionBlueprintForKeyAction::run('hero');
    $section = Section::factory()
        ->site($site)
        ->blueprint($blueprint)
        ->withTranslations($language, [
            'title' => 'Lazy-safe Hero',
            'content' => '<p>Hydrated summary</p>',
        ])
        ->create([
            'name' => 'Hydrated hero section',
            'visible_until' => now()->addDay(),
        ]);

    $widget = Widget::factory()->create(['key' => 'lazy-safe-hero-widget']);
    $layout = Layout::factory()->site($site)->create([
        'containers' => [
            'main' => ['widgets' => [['widget_key' => $widget->key, 'occurrence' => 1]]],
        ],
    ]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create();

    WidgetAsset::factory()
        ->widget($widget)
        ->asset($section)
        ->create(['order' => 1]);

    Model::preventLazyLoading();

    try {
        $graph = BuildPublicLayoutGraphAction::run($layout, $page, $language, includeHtml: true);
    } finally {
        Model::preventLazyLoading(false);
    }

    $widgetData = $graph->containers[0]->widgets[0];
    $sectionPayload = publicLayoutGraphSectionPayload($widgetData);

    expect($sectionPayload['title'] ?? null)->toBe('Lazy-safe Hero');
});

it('reuses section render payloads for top-level public widget html', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->id]);
    $blueprint = EnsureSectionBlueprintForKeyAction::run('content');
    $section = Section::factory()
        ->site($site)
        ->blueprint($blueprint)
        ->withTranslations($language, [
            'title' => 'Budget Copy',
            'content' => '<p>Rendered once for the widget payload.</p>',
        ])
        ->create([
            'name' => 'Budget section',
            'visible_until' => now()->addDay(),
        ]);

    $widget = Widget::factory()->create(['key' => 'budget-widget']);
    $layout = Layout::factory()->site($site)->create([
        'containers' => [
            'main' => ['widgets' => [['widget_key' => $widget->key, 'occurrence' => 1]]],
        ],
    ]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create();

    WidgetAsset::factory()->widget($widget)->asset($section)->create(['order' => 1]);

    $graph = BuildPublicLayoutGraphAction::run($layout, $page, $language, includeHtml: true);
    $widgetData = $graph->containers[0]->widgets[0];
    $sectionPayload = publicLayoutGraphSectionPayload($widgetData);

    expect($sectionPayload['html'] ?? null)
        ->toBe($widgetData->html)
        ->toContain('Rendered once for the widget payload.');
});

/**
 * @return array<string, mixed>
 */
function publicLayoutGraphSectionPayload(PublicLayoutWidgetData $widgetData): array
{
    $sections = $widgetData->data['sections'] ?? [];
    $section = is_array($sections) ? ($sections[0] ?? null) : null;

    throw_unless(is_array($section), RuntimeException::class, 'Expected public layout section payload.');

    $payload = [];

    foreach ($section as $key => $value) {
        if (is_string($key)) {
            $payload[$key] = $value;
        }
    }

    return $payload;
}

it('does not expose pending or expired section assets in public layout widget payloads', function (): void {
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

    $widget = Widget::factory()->create(['key' => 'hero-widget']);
    $layout = Layout::factory()->site($site)->create([
        'containers' => [
            'main' => ['widgets' => [['widget_key' => $widget->key, 'occurrence' => 1]]],
        ],
    ]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create();

    WidgetAsset::factory()->widget($widget)->asset($pendingSection)->create(['order' => 1]);
    WidgetAsset::factory()->widget($widget)->asset($expiredSection)->create(['order' => 2]);

    $graph = BuildPublicLayoutGraphAction::run($layout, $page, $language, includeHtml: true);
    $widgetData = $graph->containers[0]->widgets[0];

    expect($widgetData->data)->not->toHaveKey('sections')
        ->and($widgetData->html)->toBeNull();
});
