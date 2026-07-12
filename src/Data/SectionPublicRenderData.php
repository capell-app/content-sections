<?php

declare(strict_types=1);

namespace Capell\ContentSections\Data;

use Spatie\LaravelData\Data;

final class SectionPublicRenderData extends Data
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $key,
        public readonly string $component,
        public readonly ?string $title,
        public readonly ?string $summary,
        public readonly array $meta,
        public readonly ?string $linkText,
        public readonly ?string $url,
        public readonly string $html,
    ) {}
}
