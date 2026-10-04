<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Support\SectionRegistry;
use Capell\Core\Contracts\PackageLifecycleAction;
use Capell\Core\Contracts\ProgressReporter;
use Capell\Core\Data\PackageData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Override;

final class InstallContentSectionsPackageAction implements PackageLifecycleAction
{
    use AsFake;
    use AsObject;

    /** @param array<string, mixed> $arguments */
    #[Override]
    public function handle(PackageData $package, array $arguments = [], ?ProgressReporter $reporter = null): void
    {
        // The install lifecycle runs before installed providers boot their subjects.
        RegisterSectionBlueprintSubjectsAction::run();
        $registry = resolve(SectionRegistry::class);
        RegisterDefaultSectionsAction::run($registry);

        foreach ($registry->all() as $definition) {
            EnsureSectionBlueprintForKeyAction::run($definition->key);
        }
    }
}
