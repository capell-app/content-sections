@php
    use Capell\ContentSections\Actions\BuildSectionAssetRenderDataAction;
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
    $sectionClass = trim('section-asset ' . \Illuminate\Support\Arr::toCssClasses(\Illuminate\Support\Arr::wrap($attributes->get('class'))));
    $attributes = $attributes->except('class');
    $renderData = BuildSectionAssetRenderDataAction::run(
        asset: $asset,
        componentItem: $componentItem,
        withImage: $withImage,
        withLinkText: $withLinkText,
        withSummary: $withSummary,
        withUrl: $withUrl,
    );
@endphp
{{-- format-ignore-end --}}
<x-dynamic-component
    :component="$renderData->componentItem"
    :$asset
    :$loop
    :$size
    :color="$renderData->color"
    :icon="$renderData->icon"
    :image="$renderData->image"
    :link-text="$renderData->linkText"
    :meta="$renderData->meta"
    :summary="$renderData->summary"
    :title="$renderData->title"
    :url="$renderData->url"
    class="capell-section-asset {{ $sectionClass }}"
/>
