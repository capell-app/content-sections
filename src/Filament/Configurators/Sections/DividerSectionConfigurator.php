<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Configurators\Sections;

use Override;

class DividerSectionConfigurator extends PopularSectionConfigurator
{
    protected function sectionKey(): string
    {
        return 'divider';
    }

    #[Override]
    protected function hasMainContentField(): bool
    {
        return false;
    }
}
