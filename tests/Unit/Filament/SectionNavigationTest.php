<?php

declare(strict_types=1);

use Capell\ContentSections\Filament\Resources\Sections\SectionResource;

it('nests reusable sections below top-level pages navigation', function (): void {
    expect(SectionResource::getNavigationGroup())->toBe((string) __('capell-admin::navigation.group_websites'))
        ->and(SectionResource::getNavigationParentItem())->toBeNull()
        ->and(SectionResource::getNavigationSort())->toBe(5);
});
