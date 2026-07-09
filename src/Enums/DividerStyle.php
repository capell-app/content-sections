<?php

declare(strict_types=1);

namespace Capell\ContentSections\Enums;

use Filament\Support\Contracts\HasLabel;

enum DividerStyle: string implements HasLabel
{
    case Line = 'line';
    case Space = 'space';
    case Dots = 'dots';

    public function getLabel(): string
    {
        return match ($this) {
            self::Line => __('capell-content-sections::generic.divider_line'),
            self::Space => __('capell-content-sections::generic.divider_space'),
            self::Dots => __('capell-content-sections::generic.divider_dots'),
        };
    }
}
