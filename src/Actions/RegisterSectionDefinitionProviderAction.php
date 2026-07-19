<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Contracts\SectionDefinitionProvider;
use Capell\ContentSections\Data\SectionDefinitionData;
use Capell\ContentSections\Support\SectionRegistry;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

class RegisterSectionDefinitionProviderAction
{
    use AsFake;
    use AsObject;

    public function handle(SectionRegistry $registry, SectionDefinitionProvider $provider): void
    {
        foreach ($provider->definitions() as $definition) {
            $existing = $registry->get($definition->key);

            if ($existing instanceof SectionDefinitionData
                && get_object_vars($existing) === get_object_vars($definition)) {
                continue;
            }

            $registry->register($definition);
        }
    }
}
