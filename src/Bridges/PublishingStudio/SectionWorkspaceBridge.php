<?php

declare(strict_types=1);

namespace Capell\ContentSections\Bridges\PublishingStudio;

use Capell\ContentSections\Actions\FinalizeSectionPublishAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Facades\CapellCore;
use Capell\PublishingStudio\Actions\SaveRecordDraftAction;
use Capell\PublishingStudio\Enums\WorkspaceStatusEnum;
use Capell\PublishingStudio\Filament\Actions\PublishingRevisionsHeaderAction;
use Capell\PublishingStudio\Models\Workspace;
use Capell\PublishingStudio\Publisher;
use Capell\PublishingStudio\WorkspaceRegistry;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthenticatedUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * The only place Content Sections touches Publishing Studio's workspaces, drafts and publishing.
 * Every method does nothing, and returns null or false, unless Publishing Studio is installed.
 */
final class SectionWorkspaceBridge
{
    public static function registerWorkspace(): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        WorkspaceRegistry::register(
            Section::class,
            cloneUsing: fn (Model $source, Workspace $workspace): Model => CloneSectionIntoWorkspaceAction::run($source, $workspace),
            finalizeOnPublish: fn (Model $record): Model => FinalizeSectionPublishAction::run($record),
        );
    }

    public static function revisionsHeaderAction(): ?object
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return null;
        }

        return PublishingRevisionsHeaderAction::make();
    }

    public static function workspaceFor(Model $record): ?Model
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return null;
        }

        if (! Schema::hasTable((new Workspace)->getTable())) {
            return null;
        }

        $workspaceId = $record->getAttribute('workspace_id');

        return is_numeric($workspaceId) ? Workspace::query()->find((int) $workspaceId) : null;
    }

    public static function mayPublish(?Model $workspace): bool
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return false;
        }

        return $workspace instanceof Workspace && Gate::allows('publish', $workspace);
    }

    public static function isReadyToPublish(?Model $workspace): bool
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return false;
        }

        return $workspace instanceof Workspace
            && in_array($workspace->status, [WorkspaceStatusEnum::Approved, WorkspaceStatusEnum::Scheduled], true);
    }

    /** Returns false, publishing nothing, when the model is not a workspace. */
    public static function publish(Application $app, ?Model $workspace, ?Authenticatable $actor): bool
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return false;
        }

        if (! $workspace instanceof Workspace) {
            return false;
        }

        Gate::authorize('publish', $workspace);

        $app->make(Publisher::class)->publish($workspace, $actor);

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Closure(Section): void  $saveRelationships
     * @return array{workspaceName: string, workspaceId: int, recordKey: mixed}|null
     */
    public static function saveDraft(Section $record, array $data, AuthenticatedUser $user, Closure $saveRelationships): ?array
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return null;
        }

        // Related copies are made while the form state is read, so they share the draft's transaction.
        $result = DB::transaction(fn () => SaveRecordDraftAction::run(
            record: $record,
            data: $data,
            user: $user,
            saveRelationships: $saveRelationships,
        ));

        return [
            'workspaceName' => (string) $result->workspace->name,
            'workspaceId' => (int) $result->workspace->id,
            'recordKey' => $result->record->getKey(),
        ];
    }
}
