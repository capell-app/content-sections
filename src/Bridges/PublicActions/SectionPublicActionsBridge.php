<?php

declare(strict_types=1);

namespace Capell\ContentSections\Bridges\PublicActions;

use Capell\Core\Facades\CapellCore;
use Capell\PublicActions\Actions\ListPublicActionOptionsAction;

/**
 * The only place Content Sections touches the optional Public Actions package.
 */
final class SectionPublicActionsBridge
{
    /** @return array<string, string> */
    public static function options(): array
    {
        if (! (CapellCore::isPackageAvailable('capell-app/public-actions') && CapellCore::isPackageInstalled('capell-app/public-actions'))) {
            return [];
        }

        return ListPublicActionOptionsAction::run();
    }
}
