<?php

declare(strict_types=1);

namespace Capell\ContentSections\Data;

/**
 * Public-safe description of one section media item: a plain URL and optional alt text.
 */
final readonly class SectionMediaData
{
    public function __construct(
        public string $url,
        public ?string $alt = null,
    ) {}

    /**
     * @return array{url: string, alt: string|null}
     */
    public function toArray(): array
    {
        return ['url' => $this->url, 'alt' => $this->alt];
    }
}
