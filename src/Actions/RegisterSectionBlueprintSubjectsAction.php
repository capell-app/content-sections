<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Enums\LayoutTypeEnum;
use Capell\Core\Data\BlueprintSubjectDescriptorData;
use Capell\Core\Support\BlueprintSubjectRegistry;
use Capell\Core\Support\Packages\PackageSurfaceRegistrar;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class RegisterSectionBlueprintSubjectsAction
{
    use AsFake;
    use AsObject;

    public function handle(): void
    {
        foreach (LayoutTypeEnum::cases() as $layoutType) {
            $subject = new BlueprintSubjectDescriptorData(
                key: $layoutType->value,
                label: $layoutType->getLabel(),
                modelClass: $layoutType->getModel(),
                ownerPackage: 'capell-app/content-sections',
            );
            $existing = resolve(BlueprintSubjectRegistry::class)->descriptorOrNull($subject->key);
            if ($existing !== null && $existing->toArray() === $subject->toArray()) {
                continue;
            }

            resolve(PackageSurfaceRegistrar::class)->blueprintSubject($subject);
        }
    }
}
