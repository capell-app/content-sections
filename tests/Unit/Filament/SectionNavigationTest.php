<?php

declare(strict_types=1);

use Capell\Admin\Enums\NavigationGroupEnum;
use Capell\ContentSections\Filament\Resources\Sections\SectionResource;

it('nests reusable sections below top-level pages navigation', function (): void {
    expect(SectionResource::getNavigationGroup())->toBe(NavigationGroupEnum::Website)
        ->and(SectionResource::getNavigationParentItem())->toBeNull()
        ->and(SectionResource::getNavigationSort())->toBe(5);
});
