<?php

declare(strict_types=1);

namespace Capell\ContentSections\Database\Factories;

use Capell\ContentSections\Enums\LayoutTypeEnum;
use Capell\Core\Database\Factories\BlueprintFactory;

class ContentBlueprintFactory extends BlueprintFactory
{
    public function definition(): array
    {
        return [
            ...parent::definition(),
            'type' => LayoutTypeEnum::Section->value,
        ];
    }
}
