<?php

declare(strict_types=1);

namespace Capell\ContentSections\Enums;

enum PublicSectionFragmentOutcome: string
{
    case Rendered = 'rendered';
    case Unavailable = 'unavailable';
    case AuthoringSurfaceRejected = 'authoring_surface_rejected';
    case RenderFailed = 'render_failed';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Rendered => 200,
            self::RenderFailed => 500,
            default => 404,
        };
    }
}
