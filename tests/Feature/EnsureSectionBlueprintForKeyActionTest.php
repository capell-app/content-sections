<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\EnsureSectionBlueprintForKeyAction;

it('stores Filament icon enums as complete Blade icon aliases', function (): void {
    $heroBlueprint = EnsureSectionBlueprintForKeyAction::run('hero');
    $featuresBlueprint = EnsureSectionBlueprintForKeyAction::run('features');

    expect(data_get($heroBlueprint->admin, 'icon'))
        ->toBe('heroicon-o-sparkles')
        ->and(data_get($featuresBlueprint->admin, 'icon'))
        ->toBe('heroicon-o-squares-2x2');
});
