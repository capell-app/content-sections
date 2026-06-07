<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Configurators\Sections;

class TabsSectionConfigurator extends RichSectionConfigurator
{
    protected function sectionKey(): string
    {
        return 'tabs';
    }
}
