<?php

declare(strict_types=1);

use Capell\ContentSections\Models\Section;
use Filament\Support\Icons\Heroicon;

return [
    'screenshot_fixtures_enabled' => env('CAPELL_CONTENT_SECTIONS_SCREENSHOT_FIXTURES_ENABLED', false),

    'assets' => [
        'section' => [
            'color' => 'info',
            'icon' => Heroicon::OutlinedClipboardDocumentList,
            'model' => Section::class,
        ],
    ],
];
