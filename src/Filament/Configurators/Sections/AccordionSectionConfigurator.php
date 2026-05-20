<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Configurators\Sections;

use Override;

class AccordionSectionConfigurator extends PopularSectionConfigurator
{
    protected function sectionKey(): string
    {
        return 'accordion';
    }

    #[Override]
    protected function hasMainContentField(): bool
    {
        return false;
    }
}
