<?php

declare(strict_types=1);

namespace Capell\ContentSections\Fragments;

use Capell\Frontend\Contracts\Fragments\PublicFragmentReferenceCodec;
use Capell\Frontend\Contracts\Fragments\PublicFragmentUrlResolver;
use Capell\Frontend\Data\Fragments\PublicFragmentReferenceData;
use Capell\Frontend\Exceptions\PublicFragmentReferenceInvalid;
use Override;

final readonly class ContentSectionsFragmentUrlResolver implements PublicFragmentUrlResolver
{
    public const string OWNER = 'content-sections';

    public function __construct(private PublicFragmentReferenceCodec $codec) {}

    #[Override]
    public function owner(): string
    {
        return self::OWNER;
    }

    #[Override]
    public function url(PublicFragmentReferenceData $reference): string
    {
        if ($reference->owner !== self::OWNER) {
            throw new PublicFragmentReferenceInvalid;
        }

        return route('capell-content-sections.fragments.show', ['reference' => $this->codec->encode($reference)], absolute: false);
    }
}
