<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('screenshot-fixtures/content-sections')
    ->name('capell-content-sections.screenshot-fixtures.')
    ->group(function (): void {
        Route::view('/section-selector-modal', 'capell-content-sections::screenshots.section-selector-modal')
            ->name('section-selector-modal');

        Route::view('/section-widget-gallery', 'capell-content-sections::screenshots.section-widget-gallery')
            ->name('section-widget-gallery');
    });
