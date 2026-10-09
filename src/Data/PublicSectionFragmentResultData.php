<?php

declare(strict_types=1);

namespace Capell\ContentSections\Data;

use Capell\ContentSections\Enums\PublicSectionFragmentOutcome;
use InvalidArgumentException;

final readonly class PublicSectionFragmentResultData
{
    public function __construct(public PublicSectionFragmentOutcome $outcome, public ?string $html = null)
    {
        if (($outcome === PublicSectionFragmentOutcome::Rendered && (! is_string($html) || trim($html) === ''))
            || ($outcome !== PublicSectionFragmentOutcome::Rendered && $html !== null)) {
            throw new InvalidArgumentException('Fragment outcome and HTML must agree.');
        }
    }
}
