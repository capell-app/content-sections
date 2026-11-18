<?php

declare(strict_types=1);

namespace Capell\ContentSections\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Override;

final class ContentSectionsConsoleCommandsContribution implements ExtensionContribution
{
    #[Override]
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
