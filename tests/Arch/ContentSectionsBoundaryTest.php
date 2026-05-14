<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('does not depend on removed layout-builder package internals', function (): void {
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

    expect($violations)->toBeEmpty();
});

it('treats layout builder as an optional integration package', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2) . '/capell.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['dependencies']['requires'] ?? [])->not->toContain('capell-app/layout-builder')
        ->and($manifest['dependencies']['optional'] ?? [])->toContain('capell-app/layout-builder');
});
