<?php

declare(strict_types=1);

it('does not expose screenshot fixture routes unless explicitly enabled', function (string $path): void {
    config()->set('capell-content-sections.screenshot_fixtures_enabled', false);

    $this->get($path)
        ->assertNotFound()
        ->assertDontSee('Choose a reusable section')
        ->assertDontSee('Content Sections widget gallery');
})->with([
    'selector modal' => ['/screenshot-fixtures/content-sections/section-selector-modal'],
    'widget gallery' => ['/screenshot-fixtures/content-sections/section-widget-gallery'],
]);

it('renders dedicated screenshot fixture states for the selector and widget gallery when enabled', function (string $path, string $expectedText): void {
    config()->set('capell-content-sections.screenshot_fixtures_enabled', true);

    $this->get($path)
        ->assertOk()
        ->assertSee($expectedText)
        ->assertDontSee('CapellFrontendAuthoring')
        ->assertDontSee('signed');
})->with([
    'selector modal' => ['/screenshot-fixtures/content-sections/section-selector-modal', 'Choose a reusable section'],
    'widget gallery' => ['/screenshot-fixtures/content-sections/section-widget-gallery', 'Content Sections widget gallery'],
]);
