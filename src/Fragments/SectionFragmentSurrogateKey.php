<?php

declare(strict_types=1);

namespace Capell\ContentSections\Fragments;

use Capell\ContentSections\Models\Section;

/**
 * The surrogate key shared fragments of one section are cached under, so anything that changes what
 * the section renders can invalidate exactly those fragments.
 */
final class SectionFragmentSurrogateKey
{
    public static function for(Section $section): string
    {
        return self::forId($section->id);
    }

    public static function forId(int|string $sectionId): string
    {
        return (new Section)->getMorphClass() . '-' . $sectionId;
    }
}
