<?php

declare(strict_types=1);

namespace Capell\ContentSections\Enums;

use Filament\Support\Contracts\HasLabel;

enum ActionColor: string implements HasLabel
{
    case Primary = 'primary';
    case Secondary = 'secondary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Primary => __('capell-admin::generic.primary'),
            self::Secondary => __('capell-admin::generic.secondary'),
        };
    }
}
