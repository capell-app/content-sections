<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Resources\Sections\Pages;

use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\ContentSections\Actions\BuildSectionCreateFormDataAction;
use Capell\ContentSections\Enums\ResourceEnum;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateSection extends CreateRecord
{
    /**
     * Related copies are made during the save, so they must commit or roll back with the section.
     */
    protected ?bool $hasDatabaseTransactions = true;

    #[Override]
    public static function getResource(): string
    {
        return AdminSurfaceLookup::resource(ResourceEnum::Section);
    }

    #[Override]
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill(BuildSectionCreateFormDataAction::run($this->data ?? []));

        $this->callHook('afterFill');
    }
}
