<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\Admin\Data\Pages\PublishVisibilityActionResultData;
use Capell\ContentSections\Models\Section;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsObject;

final class CancelScheduledSectionUnpublishAction
{
    use AsObject;

    public function handle(Section $section, User $actor): PublishVisibilityActionResultData
    {
        $response = Gate::forUser($actor)->inspect('update', $section);

        if (! $response->allowed()) {
            return PublishVisibilityActionResultData::skipped('unauthorized');
        }

        if (! $section->visible_until?->isFuture()) {
            return PublishVisibilityActionResultData::skipped('not_scheduled');
        }

        $section->visible_until = null;
        $section->save();

        $this->invalidateFrontendCache($section);

        return PublishVisibilityActionResultData::changed();
    }

    private function invalidateFrontendCache(Section $section): void
    {
        $registryClass = 'Capell\\Frontend\\Support\\Cache\\CacheInvalidationRegistry';

        if (! class_exists($registryClass)) {
            return;
        }

        $registry = app($registryClass);

        if (! is_object($registry) || ! method_exists($registry, 'invalidateChangedModel')) {
            return;
        }

        $registry->invalidateChangedModel($section);
    }
}
