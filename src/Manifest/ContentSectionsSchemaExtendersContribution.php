<?php

declare(strict_types=1);

namespace Capell\ContentSections\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class ContentSectionsSchemaExtendersContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
