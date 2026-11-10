<?php

declare(strict_types=1);

namespace Capell\ContentSections\Tests\Support;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

final class ContentAssetOptionSchemaHarness extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<int, array<string, mixed>> */
    public array $mountedActions = [];
}
