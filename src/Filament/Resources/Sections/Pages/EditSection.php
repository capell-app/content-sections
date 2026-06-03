<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Resources\Sections\Pages;

use Capell\Admin\Filament\Actions\DeleteAction;
use Capell\Admin\Filament\Actions\ReplicateAction;
use Capell\Admin\Filament\Concerns\HasAncestorBreadcrumbs;
use Capell\Admin\Filament\Concerns\HasBlueprintRelationManagers;
use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\ContentSections\Actions\CancelScheduledSectionUnpublishAction;
use Capell\ContentSections\Actions\ReplicateContentAction;
use Capell\ContentSections\Actions\UnpublishSectionAction;
use Capell\ContentSections\Enums\LivewireComponentsEnum;
use Capell\ContentSections\Enums\ResourceEnum;
use Capell\ContentSections\Filament\Actions\CreateContentAction;
use Capell\ContentSections\Filament\Resources\Sections\Widgets\SectionAlertsWidget;
use Capell\ContentSections\Models\Section;
use Capell\PublishingStudio\Actions\SaveRecordDraftAction;
use Capell\PublishingStudio\Enums\WorkspaceStatusEnum;
use Capell\PublishingStudio\Filament\Actions\PublishingRevisionsHeaderAction;
use Capell\PublishingStudio\Models\Workspace;
use Capell\PublishingStudio\Publisher;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Widgets\Widget;
use Howdu\FilamentRecordSwitcher\Filament\Concerns\HasRecordSwitcher;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Auth\User as AuthenticatedUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Override;
use Throwable;

/**
 * @property Section $record
 */
#[On('$refresh')]
class EditSection extends EditRecord
{
    use HasAncestorBreadcrumbs;
    use HasBlueprintRelationManagers;
    use HasRecordSwitcher {
        afterSave as recordSwitcherAfterSave;
    }

    #[Override]
    public static function getResource(): string
    {
        return AdminSurfaceLookup::resource(ResourceEnum::Section);
    }

    #[Override]
    public function getTitle(): string|Htmlable
    {
        if (filled(static::$title)) {
            return static::$title;
        }

        $recordTitle = $this->getRecordTitle();

        return new HtmlString(
            __(
                'capell-content-sections::heading.edit_content_record',
                ['name' => Str::limit($recordTitle instanceof Htmlable ? $recordTitle->toHtml() : $recordTitle, 40)],
            ),
        );
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        $blueprint = $this->record->blueprint;

        if ($blueprint === null) {
            return null;
        }

        return __('capell-content-sections::heading.content_blueprint', [
            'type' => $blueprint->name,
        ]);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        /** @var array<Action|ActionGroup> $actions */
        $actions = array_values(array_filter([
            $this->saveAsDraftAction(),
            $this->unpublishAction(),
            $this->cancelScheduledUnpublishAction(),
            $this->publishAction(),
            $this->publishingRevisionsAction(),
            RestoreAction::make('restore'),
            DeleteAction::make('delete'),
            ForceDeleteAction::make('forceDelete'),
            ActionGroup::make([
                CreateContentAction::make('create')
                    ->redirectAfterCreate(),
                ReplicateAction::make('replicate')
                    ->replicaModelAction(ReplicateContentAction::class)
                    ->hidden($this->record->trashed()),
            ]),
        ]));

        return $actions;
    }

    /** @return array<class-string<Widget>> */
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

    #[Override]
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(__('capell-content-sections::button.save_and_publish'));
    }

    private function publishingRevisionsAction(): ?object
    {
        $actionClass = PublishingRevisionsHeaderAction::class;

        if (! class_exists($actionClass)) {
            return null;
        }

        return $actionClass::make();
    }

    private function saveAsDraftAction(): ?Action
    {
        if (! class_exists(SaveRecordDraftAction::class)) {
            return null;
        }

        return Action::make('saveAsDraft')
            ->label(__('capell-content-sections::button.save_as_draft'))
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn (): bool => (int) $this->record->getAttribute('workspace_id') === 0)
            ->action(function (): void {
                $this->saveSectionDraft();
            });
    }

    private function publishAction(): ?Action
    {
        if (! class_exists(Publisher::class)) {
            return null;
        }

        return Action::make('publish')
            ->label(__('capell-content-sections::button.publish'))
            ->icon('heroicon-o-rocket-launch')
            ->color('primary')
            ->visible(fn (): bool => (int) $this->record->getAttribute('workspace_id') > 0)
            ->authorize(fn (): bool => ($workspace = $this->workspace()) instanceof Workspace
                && Gate::allows('publish', $workspace))
            ->disabled(fn (): bool => ! in_array($this->workspace()?->status, [
                WorkspaceStatusEnum::Approved,
                WorkspaceStatusEnum::Scheduled,
            ], true))
            ->requiresConfirmation()
            ->modalHeading(__('capell-content-sections::heading.publish_section'))
            ->action(function (): void {
                $workspace = $this->workspace();

                if (! $workspace instanceof Workspace) {
                    return;
                }

                Gate::authorize('publish', $workspace);

                try {
                    resolve(Publisher::class)->publish($workspace, auth()->user());
                } catch (Throwable $throwable) {
                    Notification::make()
                        ->title(__('capell-content-sections::message.publish_failed'))
                        ->body($throwable->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('capell-content-sections::message.published'))
                    ->success()
                    ->send();

                $this->redirect(
                    static::getResource()::getUrl('edit', ['record' => $this->record->getKey()]),
                    navigate: false,
                );
            });
    }

    private function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label(__('capell-content-sections::button.unpublish'))
            ->icon('heroicon-o-exclamation-circle')
            ->color('warning')
            ->visible(fn (): bool => (int) $this->record->getAttribute('workspace_id') === 0
                && ! $this->record->isPending()
                && ! $this->record->isExpired())
            ->authorize(fn (): bool => Gate::allows('update', $this->record))
            ->requiresConfirmation()
            ->modalHeading(__('capell-content-sections::button.unpublish'))
            ->modalDescription(__('capell-content-sections::message.unpublish_section_confirmation'))
            ->action(function (): void {
                $user = auth()->user();

                if (! $user instanceof AuthenticatedUser) {
                    return;
                }

                $result = UnpublishSectionAction::run($this->record, $user);

                if (! $result->changed) {
                    return;
                }

                $this->record->refresh();

                Notification::make()
                    ->title(__('capell-content-sections::message.unpublished'))
                    ->success()
                    ->send();

                $this->dispatch('refresh-alerts')->to(LivewireComponentsEnum::ContentAssetsTable->value);
            });
    }

    private function cancelScheduledUnpublishAction(): Action
    {
        return Action::make('cancelScheduledUnpublish')
            ->label(__('capell-content-sections::button.cancel_scheduled_unpublish'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (): bool => (int) $this->record->getAttribute('workspace_id') === 0
                && $this->record->visible_until?->isFuture() === true)
            ->authorize(fn (): bool => Gate::allows('update', $this->record))
            ->requiresConfirmation()
            ->modalHeading(__('capell-content-sections::button.cancel_scheduled_unpublish'))
            ->modalDescription(__('capell-content-sections::message.cancel_scheduled_unpublish_confirmation'))
            ->action(function (): void {
                $user = auth()->user();

                if (! $user instanceof AuthenticatedUser) {
                    return;
                }

                $result = CancelScheduledSectionUnpublishAction::run($this->record, $user);

                if (! $result->changed) {
                    return;
                }

                $this->record->refresh();

                Notification::make()
                    ->title(__('capell-content-sections::message.scheduled_unpublish_cancelled'))
                    ->success()
                    ->send();

                $this->dispatch('refresh-alerts')->to(LivewireComponentsEnum::ContentAssetsTable->value);
            });
    }

    private function saveSectionDraft(): void
    {
        $this->authorize('update', $this->record);

        $user = auth()->user();

        if (! $user instanceof AuthenticatedUser) {
            return;
        }

        $data = $this->form->getState();
        $result = SaveRecordDraftAction::run(
            record: $this->record,
            data: $data,
            user: $user,
            saveRelationships: function (Section $draft): void {
                $this->form->model($draft)->saveRelationships();
            },
        );

        Notification::make()
            ->title(__('capell-content-sections::message.saved_as_draft', ['workspace' => $result->workspace->name]))
            ->success()
            ->send();

        $this->dispatch('workspace-changed', workspaceId: $result->workspace->id);

        $this->redirect(
            static::getResource()::getUrl('edit', ['record' => $result->record->getKey()]),
            navigate: false,
        );
    }

    private function workspace(): ?Workspace
    {
        if (! Schema::hasTable((new Workspace)->getTable())) {
            return null;
        }

        $workspaceId = $this->record->getAttribute('workspace_id');

        return $workspaceId === null ? null : Workspace::query()->find((int) $workspaceId);
    }
}
