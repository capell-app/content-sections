<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Data\SectionAssetRenderData;
use Capell\Core\Models\Contracts\Blueprintable;
use Capell\Frontend\Contracts\FrontendComponentRegistryInterface;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildSectionAssetRenderDataAction
{
    use AsFake;
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
        $meta = data_get($asset, 'meta');
        $meta = is_array($meta) ? $meta : [];

        return new SectionAssetRenderData(
            componentItem: $this->resolveComponentItem($componentItem),
            image: $withImage ? $this->image($asset) : null,
            linkText: is_string($linkText) ? $linkText : null,
            meta: $meta,
            summary: $withSummary && is_string($summary) ? $summary : null,
            title: is_string($title) ? $title : null,
            url: $withUrl ? $this->url($asset) : null,
            color: $this->metaValue($asset, $meta, 'color'),
            icon: NormalizeSectionIconAction::run($this->metaValue($asset, $meta, 'icon')),
        );
    }

    private function resolveComponentItem(string $componentItem): string
    {
        if (! interface_exists(FrontendComponentRegistryInterface::class)
            || ! app()->bound(FrontendComponentRegistryInterface::class)
            || ! app()->resolved(FrontendComponentRegistryInterface::class)) {
            return $componentItem;
        }

        return resolve(FrontendComponentRegistryInterface::class)->resolve($componentItem);
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

        if (! $linkedPage instanceof Model || ! $linkedPage->relationLoaded('pageUrl')) {
            return null;
        }

        $pageUrl = $linkedPage->getRelation('pageUrl');

        $url = is_object($pageUrl) ? data_get($pageUrl, 'full_url') : null;

        return is_string($url) ? $url : null;
    }

    private function relation(object $model, string $relation): mixed
    {
        if (! $model instanceof Model || ! $model->relationLoaded($relation)) {
            return null;
        }

        return $model->getRelation($relation);
    }

    private function plainObjectValue(object $object, string $key): mixed
    {
        if ($object instanceof Model) {
            return null;
        }

        return data_get($object, $key);
    }

    /** @param array<array-key, mixed> $meta */
    private function metaValue(object $asset, array $meta, string $key): mixed
    {
        if (method_exists($asset, 'getMeta')
            && (! $asset instanceof Blueprintable || ! $asset instanceof Model || $asset->relationLoaded('blueprint'))) {
            return $asset->getMeta($key);
        }

        return $meta[$key] ?? null;
    }
}
