<?php

declare(strict_types=1);

namespace Capell\ContentSections\Enums;

use Filament\Support\Contracts\HasLabel;

enum ActionTarget: string implements HasLabel
{
    case Blank = '_blank';

    public function getLabel(): string
    {
        return match ($this) {
            self::Blank => __('capell-admin::generic.new_tab'),
        };
    }
}
