<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Resources\Sections\Pages;

use Capell\Admin\Filament\Actions\DeleteAction;
use Capell\Admin\Filament\Actions\ReplicateAction;
use Capell\Admin\Filament\Concerns\HasAncestorBreadcrumbs;
use Capell\Admin\Filament\Concerns\HasTypeRelationManagers;
use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\ContentSections\Actions\ReplicateContentAction;
use Capell\ContentSections\Enums\LivewireComponentsEnum;
use Capell\ContentSections\Enums\ResourceEnum;
use Capell\ContentSections\Filament\Actions\CreateContentAction;
use Capell\ContentSections\Filament\Resources\Sections\SectionResource;
use Capell\ContentSections\Filament\Resources\Sections\Widgets\SectionAlertsWidget;
use Capell\ContentSections\Models\Section;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Howdu\FilamentRecordSwitcher\Filament\Concerns\HasRecordSwitcher;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Override;

/**
 * @property Section $record
 */
#[On('$refresh')]
class EditSection extends EditRecord
{
    use HasAncestorBreadcrumbs;
    use HasRecordSwitcher {
        afterSave as recordSwitcherAfterSave;
    }
    use HasTypeRelationManagers;

    /** @return class-string<SectionResource> */
    public static function getResource(): string
    {
        return AdminSurfaceLookup::resource(ResourceEnum::Section);
    }

    public function getTitle(): string|Htmlable
    {
        if (filled(static::$title)) {
            return static::$title;
        }

        return new HtmlString(
            __(
                'capell-content-sections::heading.edit_content_record',
                ['name' => Str::limit($this->getRecordTitle(), 40)],
            ),
        );
    }

    public function getSubheading(): string|Htmlable|null
    {
        $type = $this->record->type;

        if ($type === null) {
            return null;
        }

        return __('capell-content-sections::heading.content_type', [
            'type' => $type->name,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return array_values(array_filter([
            $this->publishingRevisionsAction(),
            RestoreAction::make('restore'),
            DeleteAction::make('delete'),
            ForceDeleteAction::make('forceDelete'),
            CreateContentAction::make('create')
                ->redirectAfterCreate(),
            ReplicateAction::make('replicate')
                ->replicaModelAction(ReplicateContentAction::class)
                ->hidden($this->record->trashed()),
        ]));
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            SectionAlertsWidget::class,
        ];
    }

    protected function afterSave(): void
    {
        $this->dispatch('refresh-alerts')->to(LivewireComponentsEnum::ContentAssetsTable->value);

        $this->recordSwitcherAfterSave();
    }

    private function publishingRevisionsAction(): ?object
    {
        $actionClass = 'Capell\\PublishingStudio\\Filament\\Actions\\PublishingRevisionsHeaderAction';

        if (! class_exists($actionClass)) {
            return null;
        }

        return $actionClass::make();
    }
}
