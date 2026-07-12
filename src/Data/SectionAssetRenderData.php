<?php

declare(strict_types=1);

namespace Capell\ContentSections\Data;

use Spatie\LaravelData\Data;

final class SectionAssetRenderData extends Data
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $componentItem,
        public readonly mixed $image,
        public readonly ?string $linkText,
        public readonly array $meta,
        public readonly ?string $summary,
        public readonly ?string $title,
        public readonly ?string $url,
        public readonly mixed $color,
        public readonly mixed $icon,
    ) {}
}
