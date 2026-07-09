<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::prefix('screenshot-fixtures/content-sections')
    ->name('capell-content-sections.screenshot-fixtures.')
    ->group(function (): void {
        Route::get('/section-selector-modal', static function (): View {
            abort_unless(config('capell-content-sections.screenshot_fixtures_enabled', false) === true, 404);

            return view('capell-content-sections::screenshots.section-selector-modal');
        })
            ->name('section-selector-modal');

        Route::get('/section-widget-gallery', static function (): View {
            abort_unless(config('capell-content-sections.screenshot_fixtures_enabled', false) === true, 404);

            return view('capell-content-sections::screenshots.section-widget-gallery');
        })
            ->name('section-widget-gallery');
    });
