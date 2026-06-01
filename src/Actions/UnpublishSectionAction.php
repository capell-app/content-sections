<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Data\SectionVisibilityActionResultData;
use Capell\ContentSections\Models\Section;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsObject;

final class UnpublishSectionAction
{
    use AsObject;

    public function handle(Section $section, User $actor): SectionVisibilityActionResultData
    {
        $response = Gate::forUser($actor)->inspect('update', $section);

        if (! $response->allowed()) {
            return SectionVisibilityActionResultData::skipped('unauthorized');
        }

        if ($section->isExpired() || $section->isPending()) {
            return SectionVisibilityActionResultData::skipped('not_live');
        }

        $section->visible_until = CarbonImmutable::now();
        $section->save();

        $this->invalidateFrontendCache($section);

        return SectionVisibilityActionResultData::changed();
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
