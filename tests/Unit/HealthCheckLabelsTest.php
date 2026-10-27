<?php

declare(strict_types=1);

use Capell\ContentSections\Health\ContentSectionsHealthCheck;

it('names diagnostic checks by package and plain purpose', function (): void {
    $results = ContentSectionsHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(5)
        ->and($results->pluck('id')->all())->toBe([
            'legacy.content.sections.storage.table',
            'legacy.content.sections.morph.alias',
            'legacy.content.sections.admin.resource',
            'legacy.content.sections.registry',
            'legacy.content.sections.public.payload.contributor',
        ]);

    foreach ($results as $result) {
        expect($result->label)->toStartWith('Content Sections: ')
            ->not->toMatch('/morph|contributor|registry|resource/i');
    }

    $storage = (new ContentSectionsHealthCheck)->storageTableCheck();

    expect($storage->label)->toContain('database tables present');
});
