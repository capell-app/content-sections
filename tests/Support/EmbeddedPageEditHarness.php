<?php

declare(strict_types=1);

namespace Capell\ContentSections\Tests\Support;

use Capell\Admin\Filament\Components\Forms\PageSelect;
use Capell\ContentSections\Models\Section;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use RuntimeException;

final class EmbeddedPageEditHarness extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public int $sectionId;

    public function mount(int $pageId, int $sectionId): void
    {
        $this->sectionId = $sectionId;
        $schema = $this->getSchema('form');
        if ($schema === null) {
            throw new RuntimeException('Expected page form.');
        }
        $schema->fill(['page_id' => $pageId]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->model(Section::query()->findOrFail($this->sectionId))->statePath('data')->components([
            PageSelect::make('page_id')->withEditForm()
                ->updateOptionUsing(static function (array $data, Schema $schema): void {
                    $record = $schema->getRecord();
                    if (! $record instanceof Model) {
                        throw new RuntimeException('Expected page record.');
                    }
                    $record->update($data);
                }),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }} <x-filament-actions::modals /></div>';
    }
}
