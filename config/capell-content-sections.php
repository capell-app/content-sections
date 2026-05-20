<?php

declare(strict_types=1);

use Capell\ContentSections\Models\Section;
use Filament\Support\Icons\Heroicon;

return [
    'assets' => [
        'section' => [
            'color' => 'info',
            'icon' => Heroicon::OutlinedClipboardDocumentList,
            'model' => Section::class,
        ],
    ],
];
