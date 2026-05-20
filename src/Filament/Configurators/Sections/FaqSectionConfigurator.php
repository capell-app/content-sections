<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Configurators\Sections;

use Override;

class FaqSectionConfigurator extends PopularSectionConfigurator
{
    protected function sectionKey(): string
    {
        return 'faq';
    }

    #[Override]
    protected function hasMainContentField(): bool
    {
        return false;
    }
}
