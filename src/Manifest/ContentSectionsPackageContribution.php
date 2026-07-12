<?php

declare(strict_types=1);

namespace Capell\ContentSections\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionAdminResource;
use Capell\Core\Contracts\Extensions\RegistersExtensionAsset;
use Capell\Core\Contracts\Extensions\RegistersExtensionFrontendComponent;
use Capell\Core\Contracts\Extensions\RegistersExtensionPageType;

final class ContentSectionsPackageContribution implements ExtensionContribution, RegistersExtensionAdminResource, RegistersExtensionAsset, RegistersExtensionFrontendComponent, RegistersExtensionPageType
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
