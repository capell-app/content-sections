<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\ContentSections\Actions\ResolveSectionComponentAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Contracts\PublicBlockPayloadContributor;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class SectionPublicBlockPayloadContributor implements PublicBlockPayloadContributor
{
    public function priority(): int
    {
        return 10;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): array
    {
        $sections = $this->sectionAssets($block)
            ->map(fn (WidgetAsset $blockAsset): array => $this->sectionData($blockAsset))
            ->values()
            ->all();

        if ($sections === []) {
            return [];
        }

        return ['sections' => $sections];
    }

    public function html(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): ?string
    {
        $html = $this->sectionAssets($block)
            ->map(fn (WidgetAsset $blockAsset): string => $this->renderSection($blockAsset, $this->sectionData($blockAsset)))
            ->filter(fn (string $html): bool => trim($html) !== '')
            ->implode("\n");

        return $html === '' ? null : $html;
    }

    /**
     * @return Collection<int, WidgetAsset>
     */
    private function sectionAssets(Widget $block): Collection
    {
        if (! $block->relationLoaded('assets')) {
            return collect();
        }

        $assets = $block->getRelation('assets');

        if (! $assets instanceof EloquentCollection && ! $assets instanceof Collection) {
            return collect();
        }

        return $assets
            ->filter(function (mixed $blockAsset): bool {
                if (! $blockAsset instanceof WidgetAsset) {
                    return false;
                }

                $section = $this->loadedSection($blockAsset);

                return $section instanceof Section
                    && ! $section->isPending()
                    && ! $section->isExpired();
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionData(WidgetAsset $blockAsset): array
    {
        /** @var Section $section */
        $section = $this->loadedSection($blockAsset);
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
            'meta' => $this->metaFor($section, $blockAsset),
            'linkText' => $translation?->link_text,
            'url' => $this->linkedPageUrl($section),
            'blockAsset' => [
                'id' => $blockAsset->getKey(),
                'meta' => $blockAsset->meta ?? [],
            ],
            'html' => $this->renderSection($blockAsset, [
                'component' => $component,
                'meta' => $this->metaFor($section, $blockAsset),
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
    private function renderSection(WidgetAsset $blockAsset, array $data): string
    {
        /** @var Section $section */
        $section = $this->loadedSection($blockAsset);
        if (! $section instanceof Section) {
            return '';
        }

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
    private function metaFor(Section $section, WidgetAsset $blockAsset): array
    {
        return array_replace_recursive(
            is_array($section->meta) ? $section->meta : [],
            is_array($blockAsset->meta) ? $blockAsset->meta : [],
        );
    }

    private function componentFor(Section $section): string
    {
        $blueprint = $section->relationLoaded('blueprint') ? $section->getRelation('blueprint') : null;
        $configurator = $blueprint instanceof Blueprint ? ($blueprint->admin['configurator'] ?? null) : null;

        return ResolveSectionComponentAction::run(
            configurator: is_string($configurator) ? $configurator : null,
            fallbackComponent: 'capell-content-sections::section.blocks.content',
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

    private function loadedSection(WidgetAsset $blockAsset): ?Section
    {
        if (! $blockAsset->relationLoaded('asset')) {
            return null;
        }

        $asset = $blockAsset->getRelation('asset');

        return $asset instanceof Section ? $asset : null;
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
