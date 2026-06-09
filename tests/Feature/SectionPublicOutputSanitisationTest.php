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
use Capell\Tests\Support\Concerns\CreatesAdminUser;

uses(CreatesAdminUser::class);

/**
 * @param  array<string, mixed>  $meta
 */
function placeSectionAndBuildPublicGraph(
    string $key,
    string $content,
    array $meta = [],
): PublicLayoutWidgetData {
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->id]);
    $blueprint = EnsureSectionBlueprintForKeyAction::run($key);

    $section = Section::factory()
        ->site($site)
        ->blueprint($blueprint)
        ->withTranslations($language, [
            'title' => 'Untrusted Copy',
            'content' => $content,
        ])
        ->create([
            'name' => 'Untrusted section',
            'meta' => $meta,
            'visible_until' => now()->addDay(),
        ]);

    $widget = Widget::factory()->create(['key' => 'untrusted-widget']);
    $layout = Layout::factory()->site($site)->create([
        'containers' => [
            'main' => ['widgets' => [['widget_key' => $widget->key, 'occurrence' => 1]]],
        ],
    ]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create();

    WidgetAsset::factory()->widget($widget)->asset($section)->create(['order' => 1]);

    $graph = BuildPublicLayoutGraphAction::run($layout, $page, $language, includeHtml: true);

    return $graph->containers[0]->widgets[0];
}

it('strips script tags from section summary in the anonymous public payload', function (): void {
    $widgetData = placeSectionAndBuildPublicGraph(
        'hero',
        '<p>Welcome</p><script>alert(document.cookie)</script>',
    );

    $summary = publicSectionSummary($widgetData);

    expect($summary)
        ->toContain('<p>Welcome</p>')
        ->not->toContain('<script')
        ->not->toContain('alert(document.cookie)');
});

it('strips inline event handlers from section summary in the anonymous public payload', function (): void {
    $widgetData = placeSectionAndBuildPublicGraph(
        'content',
        '<p onclick="steal()">Editorial copy</p><img src=x onerror="steal()">',
    );

    $summary = publicSectionSummary($widgetData);

    expect($summary)
        ->toContain('Editorial copy')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('steal()');
});

it('does not emit script markup in the rendered anonymous section html', function (): void {
    $widgetData = placeSectionAndBuildPublicGraph(
        'hero',
        '<p>Hero body</p><script>window.__xss=1</script>',
    );

    expect($widgetData->html)
        ->toContain('Hero body')
        ->not->toContain('<script')
        ->not->toContain('window.__xss');
});

it('keeps authenticated non-admin public payloads free of authoring markers and unsafe html', function (): void {
    $user = test()->createUser(['email' => 'frontend-visitor@example.test']);

    throw_unless(is_object($user), RuntimeException::class, 'Expected test user.');

    test()->actingAs($user);

    $widgetData = placeSectionAndBuildPublicGraph(
        'hero',
        '<p>Visitor-safe body</p><script>window.__xss=1</script>',
        ['alignment' => 'center'],
    );

    $payload = json_encode($widgetData->data, JSON_THROW_ON_ERROR);
    $html = $widgetData->html ?? '';

    expect($payload)
        ->toContain('Visitor-safe body')
        ->not->toContain('<script')
        ->not->toContain('window.__xss')
        ->not->toContain('frontend-authoring')
        ->not->toContain('signed-editor')
        ->not->toContain('capell-content-sections')
        ->and($html)
        ->toContain('Visitor-safe body')
        ->not->toContain('<script')
        ->not->toContain('window.__xss')
        ->not->toContain('frontend-authoring')
        ->not->toContain('signed-editor')
        ->not->toContain('capell-content-sections');
});

it('sanitises malicious html inside nested section meta values', function (): void {
    $widgetData = placeSectionAndBuildPublicGraph(
        'faq',
        '<p>FAQ intro</p>',
        [
            'questions' => [
                [
                    'question' => 'Is meta sanitised?',
                    'answer' => '<p>Yes.</p><script>alert(1)</script>',
                ],
            ],
        ],
    );

    $questions = publicSectionMetaItems($widgetData, 'questions');
    $answer = is_string($questions[0]['answer'] ?? null) ? $questions[0]['answer'] : '';

    expect($answer)
        ->toContain('<p>Yes.</p>')
        ->not->toContain('<script')
        ->and($widgetData->html)
        ->not->toContain('<script')
        ->not->toContain('alert(1)');
});

it('normalises untrusted icon meta before public section rendering', function (): void {
    $widgetData = placeSectionAndBuildPublicGraph(
        'features',
        '<p>Feature intro</p>',
        [
            'features' => [
                [
                    'heading' => 'Safe feature',
                    'description' => 'Safe feature copy.',
                    'icon' => 'heroicon-o-sparkles',
                ],
                [
                    'heading' => 'Unsafe feature',
                    'description' => 'Unsafe icon copy.',
                    'icon' => '../../storage/app/private/secret.svg',
                ],
            ],
        ],
    );

    $features = publicSectionMetaItems($widgetData, 'features');
    $safeIcon = is_string($features[0]['icon'] ?? null) ? $features[0]['icon'] : null;
    $unsafeIcon = is_string($features[1]['icon'] ?? null) ? $features[1]['icon'] : null;

    expect($safeIcon)->toBe('heroicon-o-sparkles')
        ->and($unsafeIcon)->toBeNull()
        ->and($widgetData->html)->toContain('Safe feature')
        ->and($widgetData->html)->toContain('Unsafe feature')
        ->and($widgetData->html)->not->toContain('../../storage');
});

it('removes unsafe section meta urls before public section rendering', function (): void {
    $widgetData = placeSectionAndBuildPublicGraph(
        'features',
        '<p>Feature intro</p>',
        [
            'features' => [
                [
                    'heading' => 'Unsafe link feature',
                    'description' => 'Unsafe link copy.',
                    'url' => 'javascript:alert(1)',
                ],
                [
                    'heading' => 'Safe link feature',
                    'description' => 'Safe link copy.',
                    'url' => '/safe-feature',
                ],
            ],
        ],
    );

    $features = publicSectionMetaItems($widgetData, 'features');
    $unsafeUrl = is_string($features[0]['url'] ?? null) ? $features[0]['url'] : null;
    $safeUrl = is_string($features[1]['url'] ?? null) ? $features[1]['url'] : null;

    expect($unsafeUrl)->toBeNull()
        ->and($safeUrl)->toBe('/safe-feature')
        ->and($widgetData->html)->toContain('/safe-feature')
        ->not->toContain('javascript:alert');
});

it('preserves legitimate rich-text markup in section summary', function (): void {
    $widgetData = placeSectionAndBuildPublicGraph(
        'content',
        '<p>Lead paragraph with <strong>bold</strong> and <a href="/about">a link</a>.</p><ul><li>One</li></ul>',
    );

    expect(publicSectionSummary($widgetData))
        ->toContain('<strong>bold</strong>')
        ->toContain('<a href="/about">a link</a>')
        ->toContain('<li>One</li>');
});

function publicSectionSummary(PublicLayoutWidgetData $widgetData): string
{
    $section = publicSectionPayload($widgetData);
    $summary = $section['summary'] ?? '';

    return is_string($summary) ? $summary : '';
}

/**
 * @return list<array<string, mixed>>
 */
function publicSectionMetaItems(PublicLayoutWidgetData $widgetData, string $key): array
{
    $section = publicSectionPayload($widgetData);
    $meta = $section['meta'] ?? [];
    $items = is_array($meta) ? ($meta[$key] ?? []) : [];

    if (! is_array($items)) {
        return [];
    }

    $normalizedItems = [];

    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }

        $normalizedItem = [];

        foreach ($item as $itemKey => $itemValue) {
            if (is_string($itemKey)) {
                $normalizedItem[$itemKey] = $itemValue;
            }
        }

        $normalizedItems[] = $normalizedItem;
    }

    return $normalizedItems;
}

/**
 * @return array<string, mixed>
 */
function publicSectionPayload(PublicLayoutWidgetData $widgetData): array
{
    $sections = $widgetData->data['sections'] ?? [];
    $section = is_array($sections) ? ($sections[0] ?? null) : null;

    throw_unless(is_array($section), RuntimeException::class, 'Expected public section payload.');

    $payload = [];

    foreach ($section as $key => $value) {
        if (is_string($key)) {
            $payload[$key] = $value;
        }
    }

    return $payload;
}
