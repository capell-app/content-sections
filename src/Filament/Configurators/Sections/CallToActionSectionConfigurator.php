<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Configurators\Sections;

class CallToActionSectionConfigurator extends RichSectionConfigurator
{
    protected function sectionKey(): string
    {
        return 'call_to_action';
    }
}
