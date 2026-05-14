<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\ContentSections\Actions\ResolveSectionComponentAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Translation;
use Capell\Core\Models\Widget;
use Capell\Core\Models\WidgetAsset;
use Capell\LayoutBuilder\Contracts\PublicWidgetPayloadContributor;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class SectionPublicWidgetPayloadContributor implements PublicWidgetPayloadContributor
{
    public function priority(): int
    {
        return 10;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Widget $widget, Page $page, Language $language, string $containerKey, int $occurrence): array
    {
        $sections = $this->sectionAssets($widget)
            ->map(fn (WidgetAsset $widgetAsset): array => $this->sectionData($widgetAsset))
            ->values()
            ->all();

        if ($sections === []) {
            return [];
        }

        return ['sections' => $sections];
    }

    public function html(Widget $widget, Page $page, Language $language, string $containerKey, int $occurrence): ?string
    {
        $html = $this->sectionAssets($widget)
            ->map(fn (WidgetAsset $widgetAsset): string => $this->renderSection($widgetAsset))
            ->filter(fn (string $html): bool => trim($html) !== '')
            ->implode("\n");

        return $html === '' ? null : $html;
    }

    /**
     * @return Collection<int, WidgetAsset>
     */
    private function sectionAssets(Widget $widget): Collection
    {
        $assets = $widget->getRelationValue('assets');

        if (! $assets instanceof EloquentCollection && ! $assets instanceof Collection) {
            return collect();
        }

        return $assets
            ->filter(fn (mixed $widgetAsset): bool => $widgetAsset instanceof WidgetAsset && $widgetAsset->asset instanceof Section)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionData(WidgetAsset $widgetAsset): array
    {
        /** @var Section $section */
        $section = $widgetAsset->asset;
        $translation = $this->translationFor($section);
        $component = $this->componentFor($section);

        return [
            'id' => $section->getKey(),
            'key' => $section->type?->key ?? Str::slug($section->name),
            'component' => $component,
            'title' => $translation?->label ?? $section->name,
            'summary' => $this->summaryFor($translation),
            'meta' => $this->metaFor($section, $widgetAsset),
            'linkText' => $translation?->link_text,
            'url' => $section->linkedPage?->pageUrl?->full_url,
            'widgetAsset' => [
                'id' => $widgetAsset->getKey(),
                'meta' => $widgetAsset->meta ?? [],
            ],
        ];
    }

    private function renderSection(WidgetAsset $widgetAsset): string
    {
        /** @var Section $section */
        $section = $widgetAsset->asset;
        $data = $this->sectionData($widgetAsset);

        return Blade::render(
            '<x-dynamic-component :component="$component" :asset="$asset" :meta="$meta" :summary="$summary" :title="$title" :link-text="$linkText" :url="$url" />',
            [
                'component' => $data['component'],
                'asset' => $section,
                'meta' => $data['meta'],
                'summary' => new HtmlString((string) ($data['summary'] ?? '')),
                'title' => $data['title'],
                'linkText' => $data['linkText'],
                'url' => $data['url'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function metaFor(Section $section, WidgetAsset $widgetAsset): array
    {
        return array_replace_recursive(
            is_array($section->meta) ? $section->meta : [],
            is_array($widgetAsset->meta) ? $widgetAsset->meta : [],
        );
    }

    private function componentFor(Section $section): string
    {
        $configurator = $section->type?->admin['configurator'] ?? null;

        return ResolveSectionComponentAction::run(
            configurator: is_string($configurator) ? $configurator : null,
            fallbackComponent: 'capell-content-sections::section.blocks.content',
        );
    }

    private function translationFor(Section $section): ?Translation
    {
        $translation = $section->getRelationValue('translation');

        return $translation instanceof Translation ? $translation : null;
    }

    private function summaryFor(?Translation $translation): ?string
    {
        if (! $translation instanceof Translation) {
            return null;
        }

        if (is_string($translation->content) && $translation->content !== '') {
            return $translation->content;
        }

        return $translation->summary;
    }
}
