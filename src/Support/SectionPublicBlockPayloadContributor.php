<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\ContentSections\Actions\ResolveSectionComponentAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Contracts\PublicBlockPayloadContributor;
use Capell\LayoutBuilder\Models\Block;
use Capell\LayoutBuilder\Models\BlockAsset;
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
    public function data(Block $block, Page $page, Language $language, string $containerKey, int $occurrence): array
    {
        $sections = $this->sectionAssets($block)
            ->map(fn (BlockAsset $blockAsset): array => $this->sectionData($blockAsset))
            ->values()
            ->all();

        if ($sections === []) {
            return [];
        }

        return ['sections' => $sections];
    }

    public function html(Block $block, Page $page, Language $language, string $containerKey, int $occurrence): ?string
    {
        $html = $this->sectionAssets($block)
            ->map(fn (BlockAsset $blockAsset): string => $this->renderSection($blockAsset, $this->sectionData($blockAsset)))
            ->filter(fn (string $html): bool => trim($html) !== '')
            ->implode("\n");

        return $html === '' ? null : $html;
    }

    /**
     * @return Collection<int, BlockAsset>
     */
    private function sectionAssets(Block $block): Collection
    {
        $assets = $block->getRelationValue('assets');

        if (! $assets instanceof EloquentCollection && ! $assets instanceof Collection) {
            return collect();
        }

        return $assets
            ->filter(fn (mixed $blockAsset): bool => $blockAsset instanceof BlockAsset
                && $blockAsset->asset instanceof Section
                && ! $blockAsset->asset->isPending()
                && ! $blockAsset->asset->isExpired())
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionData(BlockAsset $blockAsset): array
    {
        /** @var Section $section */
        $section = $blockAsset->asset;
        $translation = $this->translationFor($section);
        $component = $this->componentFor($section);

        return [
            'id' => $section->getKey(),
            'key' => $section->blueprint?->key ?? Str::slug($section->name),
            'component' => $component,
            'title' => $translation?->label ?? $section->name,
            'summary' => $this->summaryFor($translation),
            'meta' => $this->metaFor($section, $blockAsset),
            'linkText' => $translation?->link_text,
            'url' => $section->linkedPage?->pageUrl?->full_url,
            'blockAsset' => [
                'id' => $blockAsset->getKey(),
                'meta' => $blockAsset->meta ?? [],
            ],
            'html' => $this->renderSection($blockAsset, [
                'component' => $component,
                'meta' => $this->metaFor($section, $blockAsset),
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
    private function renderSection(BlockAsset $blockAsset, array $data): string
    {
        /** @var Section $section */
        $section = $blockAsset->asset;

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
    private function metaFor(Section $section, BlockAsset $blockAsset): array
    {
        return array_replace_recursive(
            is_array($section->meta) ? $section->meta : [],
            is_array($blockAsset->meta) ? $blockAsset->meta : [],
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
