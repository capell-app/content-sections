<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\ContentSections\Actions\ResolveSectionComponentAction;
use Capell\ContentSections\Actions\SanitizeSectionHtmlAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Contracts\PublicLayoutWidgetPayloadContributor;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use WeakMap;

final class SectionPublicLayoutWidgetPayloadContributor implements PublicLayoutWidgetPayloadContributor
{
    /**
     * @var WeakMap<Widget, Collection<int, array<string, mixed>>>
     */
    private WeakMap $sectionDataByWidgetObject;

    public function __construct()
    {
        $this->sectionDataByWidgetObject = new WeakMap;
    }

    public function priority(): int
    {
        return 10;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Widget $widget, Page $page, Language $language, string $containerKey, int $occurrence): array
    {
        $sections = $this->sectionDataForWidget($widget)
            ->values()
            ->all();

        if ($sections === []) {
            return [];
        }

        return ['sections' => $sections];
    }

    public function html(Widget $widget, Page $page, Language $language, string $containerKey, int $occurrence): ?string
    {
        $html = $this->sectionDataForWidget($widget)
            ->map(fn (array $section): string => is_string($section['html'] ?? null) ? $section['html'] : '')
            ->filter(fn (string $html): bool => trim($html) !== '')
            ->implode("\n");

        return $html === '' ? null : $html;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sectionDataForWidget(Widget $widget): Collection
    {
        if (isset($this->sectionDataByWidgetObject[$widget])) {
            return $this->sectionDataByWidgetObject[$widget];
        }

        $this->sectionDataByWidgetObject[$widget] = $this->sectionAssets($widget)
            ->map(fn (WidgetAsset $widgetAsset): array => $this->sectionData($widgetAsset))
            ->values();

        return $this->sectionDataByWidgetObject[$widget];
    }

    /**
     * @return Collection<int, WidgetAsset>
     */
    private function sectionAssets(Widget $widget): Collection
    {
        if (! $widget->relationLoaded('assets')) {
            return collect();
        }

        $assets = $widget->getRelation('assets');

        if (! $assets instanceof EloquentCollection && ! $assets instanceof Collection) {
            return collect();
        }

        return $assets
            ->filter(function (mixed $widgetAsset): bool {
                if (! $widgetAsset instanceof WidgetAsset) {
                    return false;
                }

                $section = $this->loadedSection($widgetAsset);

                return $section instanceof Section
                    && ! $section->isPending()
                    && ! $section->isExpired();
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionData(WidgetAsset $widgetAsset): array
    {
        /** @var Section $section */
        $section = $this->loadedSection($widgetAsset);
        if (! $section instanceof Section) {
            return [];
        }

        $translation = $this->translationFor($section);
        $component = $this->componentFor($section);

        return [
            'id' => $section->getKey(),
            'key' => $this->blueprintKey($section),
            'component' => $component,
            'title' => $translation->label ?? $section->name,
            'summary' => $this->summaryFor($translation),
            'meta' => $this->metaFor($section, $widgetAsset),
            'linkText' => $translation?->link_text,
            'url' => $this->linkedPageUrl($section),
            'widgetAsset' => [
                'id' => $widgetAsset->getKey(),
                'meta' => $widgetAsset->meta ?? [],
            ],
            'html' => $this->renderSection($widgetAsset, [
                'component' => $component,
                'meta' => $this->metaFor($section, $widgetAsset),
                'summary' => $this->summaryFor($translation),
                'title' => $translation->label ?? $section->name,
                'linkText' => $translation?->link_text,
                'url' => $this->linkedPageUrl($section),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderSection(WidgetAsset $widgetAsset, array $data): string
    {
        /** @var Section $section */
        $section = $this->loadedSection($widgetAsset);
        if (! $section instanceof Section) {
            return '';
        }

        $viewData = [
            'asset' => $section,
            'meta' => $data['meta'],
            'summary' => new HtmlString((string) ($data['summary'] ?? '')),
            'title' => $data['title'],
            'linkText' => $data['linkText'],
            'url' => $data['url'],
            'attributes' => new ComponentAttributeBag,
        ];

        $viewName = $this->componentViewName((string) $data['component']);

        if ($viewName !== null && view()->exists($viewName)) {
            return view($viewName, $viewData)->render();
        }

        return Blade::render(
            '<x-dynamic-component :component="$component" :asset="$asset" :meta="$meta" :summary="$summary" :title="$title" :link-text="$linkText" :url="$url" />',
            ['component' => $data['component'], ...$viewData],
        );
    }

    private function componentViewName(string $component): ?string
    {
        if (Str::startsWith($component, 'capell-block-library::blocks.catalog.')) {
            return $component;
        }

        if (! Str::startsWith($component, 'capell-content-sections::section.widgets.')) {
            return null;
        }

        return Str::replaceFirst(
            'capell-content-sections::section.widgets.',
            'capell-content-sections::components.section.widgets.',
            $component,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function metaFor(Section $section, WidgetAsset $widgetAsset): array
    {
        $meta = array_replace_recursive(
            is_array($section->meta) ? $section->meta : [],
            is_array($widgetAsset->meta) ? $widgetAsset->meta : [],
        );

        /** @var array<string, mixed> $sanitised */
        $sanitised = SanitizeSectionHtmlAction::run($meta);

        return $sanitised;
    }

    private function componentFor(Section $section): string
    {
        $blueprint = $section->relationLoaded('blueprint') ? $section->getRelation('blueprint') : null;
        $configurator = $blueprint instanceof Blueprint ? ($blueprint->admin['configurator'] ?? null) : null;

        return ResolveSectionComponentAction::run(
            configurator: is_string($configurator) ? $configurator : null,
            fallbackComponent: 'capell-block-library::blocks.catalog.content',
        );
    }

    private function blueprintKey(Section $section): string
    {
        $blueprint = $section->relationLoaded('blueprint') ? $section->getRelation('blueprint') : null;

        return $blueprint instanceof Blueprint && is_string($blueprint->key)
            ? $blueprint->key
            : (string) Str::slug($section->name);
    }

    private function linkedPageUrl(Section $section): ?string
    {
        if (! $section->relationLoaded('linkedPage')) {
            return null;
        }

        $linkedPage = $section->getRelation('linkedPage');

        if (! $linkedPage instanceof Page || ! $linkedPage->relationLoaded('pageUrl')) {
            return null;
        }

        return $linkedPage->pageUrl?->full_url;
    }

    private function translationFor(Section $section): ?Translation
    {
        $translation = $section->getRelationValue('translation');

        return $translation instanceof Translation ? $translation : null;
    }

    private function loadedSection(WidgetAsset $widgetAsset): ?Section
    {
        if (! $widgetAsset->relationLoaded('asset')) {
            return null;
        }

        $asset = $widgetAsset->getRelation('asset');

        return $asset instanceof Section ? $asset : null;
    }

    private function summaryFor(?Translation $translation): ?string
    {
        if (! $translation instanceof Translation) {
            return null;
        }

        if (is_string($translation->content) && $translation->content !== '') {
            return SanitizeSectionHtmlAction::run($translation->content);
        }

        if (is_string($translation->summary)) {
            return SanitizeSectionHtmlAction::run($translation->summary);
        }

        return $translation->summary;
    }
}
