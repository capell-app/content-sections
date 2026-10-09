<?php

declare(strict_types=1);

use Capell\ContentSections\Models\Section;
use Filament\Support\Icons\Heroicon;

return [
    'fragments' => ['owner_aliases' => [], 'cache_seconds' => 1800],
    // The screenshot runner serves the app with PHP's built-in server, where variables that are not in a
    // .env file never reach env(); fall back to the process environment so the runner can enable the fixtures.
    'screenshot_fixtures_enabled' => filter_var(
        env('CAPELL_CONTENT_SECTIONS_SCREENSHOT_FIXTURES_ENABLED', getenv('CAPELL_CONTENT_SECTIONS_SCREENSHOT_FIXTURES_ENABLED') ?: false),
        FILTER_VALIDATE_BOOL,
    ),

    'assets' => [
        'section' => [
            'color' => 'info',
            'icon' => Heroicon::OutlinedClipboardDocumentList,
            'model' => Section::class,
        ],
    ],
];
