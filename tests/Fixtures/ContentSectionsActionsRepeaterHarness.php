<?php

declare(strict_types=1);

namespace Capell\ContentSections\Tests\Fixtures;

use Capell\ContentSections\Filament\Components\Forms\ActionsRepeater;
use Filament\Schemas\Schema;
use Override;

final class ContentSectionsActionsRepeaterHarness extends ActionsRepeater
{
    /** @var array<array-key, mixed> */
    public array $rawState = [];

    #[Override]
    public function getRawState(): mixed
    {
        return $this->rawState;
    }

    #[Override]
    public function rawState(mixed $state): static
    {
        $this->rawState = is_array($state) ? $state : [];

        return $this;
    }

    #[Override]
    public function getChildSchema($key = null): Schema
    {
        $state = is_string($key) || is_int($key)
            ? ($this->rawState[$key] ?? [])
            : [];

        return contentSectionsStateSchema(is_array($state) ? $state : []);
    }
}
