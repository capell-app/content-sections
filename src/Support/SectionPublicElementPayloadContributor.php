<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\ContentSections\Actions\ResolveSectionComponentAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Contracts\PublicElementPayloadContributor;
use Capell\LayoutBuilder\Models\Element;
use Capell\LayoutBuilder\Models\ElementAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class SectionPublicElementPayloadContributor implements PublicElementPayloadContributor
{
    public function priority(): int
    {
        return 10;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Element $element, Page $page, Language $language, string $containerKey, int $occurrence): array
    {
        $sections = $this->sectionAssets($element)
            ->map(fn (ElementAsset $elementAsset): array => $this->sectionData($elementAsset))
            ->values()
            ->all();

        if ($sections === []) {
            return [];
        }

        return ['sections' => $sections];
    }

    public function html(Element $element, Page $page, Language $language, string $containerKey, int $occurrence): ?string
    {
        $html = $this->sectionAssets($element)
            ->map(fn (ElementAsset $elementAsset): string => $this->renderSection($elementAsset, $this->sectionData($elementAsset)))
            ->filter(fn (string $html): bool => trim($html) !== '')
            ->implode("\n");

        return $html === '' ? null : $html;
    }

    /**
     * @return Collection<int, ElementAsset>
     */
    private function sectionAssets(Element $element): Collection
    {
        $assets = $element->getRelationValue('assets');

        if (! $assets instanceof EloquentCollection && ! $assets instanceof Collection) {
            return collect();
        }

        return $assets
            ->filter(fn (mixed $elementAsset): bool => $elementAsset instanceof ElementAsset
                && $elementAsset->asset instanceof Section
                && ! $elementAsset->asset->isPending()
                && ! $elementAsset->asset->isExpired())
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionData(ElementAsset $elementAsset): array
    {
        /** @var Section $section */
        $section = $elementAsset->asset;
        $translation = $this->translationFor($section);
        $component = $this->componentFor($section);

        return [
            'id' => $section->getKey(),
            'key' => $section->blueprint?->key ?? Str::slug($section->name),
            'component' => $component,
            'title' => $translation?->label ?? $section->name,
            'summary' => $this->summaryFor($translation),
            'meta' => $this->metaFor($section, $elementAsset),
            'linkText' => $translation?->link_text,
            'url' => $section->linkedPage?->pageUrl?->full_url,
            'elementAsset' => [
                'id' => $elementAsset->getKey(),
                'meta' => $elementAsset->meta ?? [],
            ],
            'html' => $this->renderSection($elementAsset, [
                'component' => $component,
                'meta' => $this->metaFor($section, $elementAsset),
                'summary' => $this->summaryFor($translation),
                'title' => $translation?->label ?? $section->name,
                'linkText' => $translation?->link_text,
                'url' => $section->linkedPage?->pageUrl?->full_url,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderSection(ElementAsset $elementAsset, array $data): string
    {
        /** @var Section $section */
        $section = $elementAsset->asset;

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
    private function metaFor(Section $section, ElementAsset $elementAsset): array
    {
        return array_replace_recursive(
            is_array($section->meta) ? $section->meta : [],
            is_array($elementAsset->meta) ? $elementAsset->meta : [],
        );
    }

    private function componentFor(Section $section): string
    {
        $configurator = $section->blueprint?->admin['configurator'] ?? null;

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
