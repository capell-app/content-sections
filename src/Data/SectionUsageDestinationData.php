<?php

declare(strict_types=1);

namespace Capell\ContentSections\Data;

use Spatie\LaravelData\Data;

/**
 * A single, currently-authorised place that references a Section: either a generic
 * attachment (something that embeds the section as content) or a Layout Builder
 * widget placement (a page or layout that positions the section through a widget).
 */
final class SectionUsageDestinationData extends Data
{
    public function __construct(
        public string $kind,
        public string $type,
        public string $title,
        public ?string $url = null,
        public ?string $site = null,
        public ?string $context = null,
    ) {}
}
