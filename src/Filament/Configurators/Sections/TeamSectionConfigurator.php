<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Configurators\Sections;

class TeamSectionConfigurator extends RichSectionConfigurator
{
    protected function sectionKey(): string
    {
        return 'team';
    }
}
