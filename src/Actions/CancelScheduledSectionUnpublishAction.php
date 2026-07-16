<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Data\SectionVisibilityActionResultData;
use Capell\ContentSections\Models\Section;
use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class CancelScheduledSectionUnpublishAction
{
    use AsFake;
    use AsObject;

    public function handle(Section $section, User $actor): SectionVisibilityActionResultData
    {
        $response = Gate::forUser($actor)->inspect('update', $section);

        if (! $response->allowed()) {
            return SectionVisibilityActionResultData::skipped('unauthorized');
        }

        if (! $section->visible_until?->isFuture()) {
            return SectionVisibilityActionResultData::skipped('not_scheduled');
        }

        $section->visible_until = null;
        $section->save();

        $this->invalidateFrontendCache($section);

        return SectionVisibilityActionResultData::changed();
    }

    private function invalidateFrontendCache(Section $section): void
    {
        $registryClass = CacheInvalidationRegistry::class;

        if (! class_exists($registryClass)) {
            return;
        }

        $registry = resolve($registryClass);

        if (! is_object($registry) || ! method_exists($registry, 'invalidateChangedModel')) {
            return;
        }

        $registry->invalidateChangedModel($section);
    }
}
