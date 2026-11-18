<?php

declare(strict_types=1);

namespace Capell\ContentSections\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RunsScheduledExtensionJob;
use Override;

final class ContentSectionsScheduledVisibilityContribution implements ExtensionContribution, RunsScheduledExtensionJob
{
    #[Override]
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
