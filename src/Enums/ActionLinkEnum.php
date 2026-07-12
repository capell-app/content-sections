<?php

declare(strict_types=1);

namespace Capell\ContentSections\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ActionLinkEnum: string implements HasIcon, HasLabel
{
    case Link = 'link';

    case Page = 'page';

    case PublicAction = 'public_action';

    public function getLabel(): string
    {
        return match ($this) {
            self::Link => __('capell-admin::generic.link'),
            self::Page => __('capell-admin::generic.page'),
            self::PublicAction => __('capell-content-sections::generic.public_action'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Link => 'heroicon-o-link',
            self::Page => 'heroicon-o-document-text',
            self::PublicAction => 'heroicon-o-bolt',
        };
    }
}
