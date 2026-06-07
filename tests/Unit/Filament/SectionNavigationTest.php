<?php

declare(strict_types=1);

use Capell\ContentSections\Filament\Resources\Sections\SectionResource;

it('nests reusable sections below top-level pages navigation', function (): void {
    expect(SectionResource::getNavigationGroup())->toBeNull()
        ->and(SectionResource::getNavigationParentItem())->toBe((string) __('capell-admin::navigation.pages'));
});
