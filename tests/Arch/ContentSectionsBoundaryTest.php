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
        ->contains('Capell\LayoutBuilder');

    foreach ($files as $file) {
        if (contentSectionsLayoutBuilderImplementationReferences($file->getContents()) !== []) {
            $violations[] = str_replace($rootPath . '/', '', $file->getPathname());
        }
    }

    expect($violations)->toEqualCanonicalizing([
        'packages/content-sections/src/Actions/BuildSectionUsageSummaryAction.php',
        'packages/content-sections/src/Filament/Resources/Sections/Tables/SectionsTable.php',
        'packages/content-sections/src/Support/SectionUsageScope.php',
        'packages/content-sections/src/Support/SectionPublicLayoutWidgetPayloadContributor.php',
    ]);
});

it('distinguishes public contracts from implementation imports and inline references', function (): void {
    expect(contentSectionsLayoutBuilderImplementationReferences('use Capell\\LayoutBuilder\\Contracts\\PublicLayoutAssetMembership;'))->toBe([])
        ->and(contentSectionsLayoutBuilderImplementationReferences('use Capell\\LayoutBuilder\\Models\\Widget;'))->toBe(['Capell\\LayoutBuilder\\Models\\Widget'])
        ->and(contentSectionsLayoutBuilderImplementationReferences('use Capell\\LayoutBuilder as Builder;'))->toBe(['Capell\\LayoutBuilder'])
        ->and(contentSectionsLayoutBuilderImplementationReferences('resolve(\\Capell\\LayoutBuilder\\Support\\Loader\\LayoutLoader::class)'))->toBe(['Capell\\LayoutBuilder\\Support\\Loader\\LayoutLoader'])
        ->and(contentSectionsLayoutBuilderImplementationReferences('Capell\\LayoutBuilder\\Actions\\ResolvePublicWidgetAssetsAction::run()'))->toBe(['Capell\\LayoutBuilder\\Actions\\ResolvePublicWidgetAssetsAction']);
});

it('declares layout builder as an explicit dependency for section widget payloads', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2) . '/capell.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['dependencies']['requires'] ?? [])->toContain('capell-app/layout-builder')
        ->and($manifest['dependencies']['supports'] ?? [])->not->toContain('capell-app/layout-builder');
});

/** @return list<string> */
function contentSectionsLayoutBuilderImplementationReferences(string $source): array
{
    preg_match_all('~Capell\\\\LayoutBuilder(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*~', $source, $matches);

    return array_values(array_filter(array_unique($matches[0]), static fn (string $reference): bool => $reference !== 'Capell\\LayoutBuilder\\Contracts' && ! str_starts_with($reference, 'Capell\\LayoutBuilder\\Contracts\\')));
}
