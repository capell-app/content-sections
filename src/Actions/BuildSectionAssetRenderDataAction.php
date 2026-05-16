<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Data\SectionAssetRenderData;
use Capell\Frontend\Contracts\FrontendComponentRegistryInterface;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildSectionAssetRenderDataAction
{
    use AsObject;

    public function handle(
        object $asset,
        string $componentItem,
        bool $withImage = false,
        bool $withLinkText = false,
        bool $withSummary = false,
        bool $withUrl = true,
    ): SectionAssetRenderData {
        $translation = $this->relation($asset, 'translation')
            ?? $this->plainObjectValue($asset, 'translation');
        $linkText = $withLinkText && is_object($translation) && method_exists($translation, 'getMeta')
            ? $translation->getMeta('link_text', __('capell-content-sections::button.read_more'))
            : null;
        $summary = is_object($translation) ? data_get($translation, 'summary') : null;
        $title = is_object($translation) ? data_get($translation, 'label') : null;

        return new SectionAssetRenderData(
            componentItem: $this->resolveComponentItem($componentItem),
            image: $withImage ? $this->image($asset) : null,
            linkText: is_string($linkText) ? $linkText : null,
            meta: is_array(data_get($asset, 'meta')) ? data_get($asset, 'meta') : [],
            summary: $withSummary && is_string($summary) ? $summary : null,
            title: is_string($title) ? $title : null,
            url: $withUrl ? $this->url($asset) : null,
            color: method_exists($asset, 'getMeta') ? $asset->getMeta('color') : null,
            icon: method_exists($asset, 'getMeta') ? $asset->getMeta('icon') : null,
        );
    }

    private function resolveComponentItem(string $componentItem): string
    {
        if (! interface_exists(FrontendComponentRegistryInterface::class) || ! app()->bound(FrontendComponentRegistryInterface::class)) {
            return $componentItem;
        }

        return app(FrontendComponentRegistryInterface::class)->resolve($componentItem);
    }

    private function image(object $asset): mixed
    {
        $image = $this->relation($asset, 'image');

        if ($image !== null) {
            return $image;
        }

        return $this->relation($asset, 'media')?->first();
    }

    private function url(object $asset): ?string
    {
        $linkedPage = $this->relation($asset, 'linkedPage');

        if (! is_object($linkedPage) || ! method_exists($linkedPage, 'relationLoaded') || ! $linkedPage->relationLoaded('pageUrl')) {
            return null;
        }

        $pageUrl = $linkedPage->getRelation('pageUrl');

        $url = is_object($pageUrl) ? data_get($pageUrl, 'full_url') : null;

        return is_string($url) ? $url : null;
    }

    private function relation(object $model, string $relation): mixed
    {
        if (! method_exists($model, 'relationLoaded') || ! $model->relationLoaded($relation)) {
            return null;
        }

        return method_exists($model, 'getRelation')
            ? $model->getRelation($relation)
            : data_get($model, $relation);
    }

    private function plainObjectValue(object $object, string $key): mixed
    {
        if (method_exists($object, 'relationLoaded')) {
            return null;
        }

        return data_get($object, $key);
    }
}
