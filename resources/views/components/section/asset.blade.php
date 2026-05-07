@php
    use Capell\Frontend\Contracts\FrontendComponentRegistryInterface;
    use Capell\Frontend\Facades\Frontend;

    $language = Frontend::language();
@endphp

@props([
    'asset',
    'componentItem',
    'size' => null,
    'loop',
    'withImage' => false,
    'withLinkText' => false,
    'withSummary' => false,
    'withUrl' => true,
])
{{-- format-ignore-start --}}
@php
    $image = null;
    if ($withImage) {
        $image = $asset->relationLoaded('image') ? $asset->image : $asset->media->first();
    }

    $sectionClass = trim('section-asset ' . \Illuminate\Support\Arr::toCssClasses(\Illuminate\Support\Arr::wrap($attributes->get('class'))));
    $attributes = $attributes->except('class');
    $resolvedComponentItem = interface_exists(FrontendComponentRegistryInterface::class) && app()->bound(FrontendComponentRegistryInterface::class)
        ? app(FrontendComponentRegistryInterface::class)->resolve($componentItem)
        : $componentItem;
@endphp
{{-- format-ignore-end --}}
<x-dynamic-component
    :component="$resolvedComponentItem"
    :$asset
    :$loop
    :$size
    :color="$asset->getMeta('color')"
    :icon="$asset->getMeta('icon')"
    :image="$image"
    :link-text="$withLinkText ? $asset->translation->getMeta('link_text', __('Read more')) : null"
    :meta="$asset->meta"
    :summary="$withSummary && $asset->translation ? $asset->translation->summary : null"
    :title="$asset->translation?->label"
    :url="$withUrl && $asset->linkedPage ? $asset->linkedPage->pageUrl?->full_url : null"
    class="{{ $sectionClass }}"
/>
