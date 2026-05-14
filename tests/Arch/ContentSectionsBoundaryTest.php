<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('only depends on the layout-builder package through public contracts', function (): void {
    $rootPath = dirname(__DIR__, 4);
    $contentSectionsPath = $rootPath . '/packages/content-sections';
    $violations = [];

    $files = (new Finder)
        ->files()
        ->in([
            $contentSectionsPath . '/src',
            $contentSectionsPath . '/resources',
            $contentSectionsPath . '/database',
        ])
        ->name(['*.php', '*.blade.php', '*.md', '*.json'])
        ->contains('Capell\\' . 'LayoutBuilder');

    foreach ($files as $file) {
        $violations[] = str_replace($rootPath . '/', '', $file->getPathname());
    }

    expect($violations)->toEqualCanonicalizing([
        'packages/content-sections/src/Providers/ContentSectionsServiceProvider.php',
        'packages/content-sections/src/Support/SectionPublicWidgetPayloadContributor.php',
    ]);
});

it('declares layout builder as an explicit dependency for section widget payloads', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2) . '/capell.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['dependencies']['requires'] ?? [])->toContain('capell-app/layout-builder')
        ->and($manifest['dependencies']['optional'] ?? [])->not->toContain('capell-app/layout-builder');
});
