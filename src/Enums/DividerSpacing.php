<?php

declare(strict_types=1);

namespace Capell\ContentSections\Enums;

use Filament\Support\Contracts\HasLabel;

enum DividerSpacing: string implements HasLabel
{
    case Small = 'sm';
    case Medium = 'md';
    case Large = 'lg';

    public function getLabel(): string
    {
        return match ($this) {
            self::Small => __('capell-content-sections::generic.spacing_sm'),
            self::Medium => __('capell-content-sections::generic.spacing_md'),
            self::Large => __('capell-content-sections::generic.spacing_lg'),
        };
    }
}
