<?php

declare(strict_types=1);

it('renders dedicated screenshot fixture states for the selector and widget gallery', function (string $path, string $expectedText): void {
    $this->get($path)
        ->assertOk()
        ->assertSee($expectedText)
        ->assertDontSee('CapellFrontendAuthoring')
        ->assertDontSee('signed');
})->with([
    'selector modal' => ['/screenshot-fixtures/content-sections/section-selector-modal', 'Choose a reusable section'],
    'widget gallery' => ['/screenshot-fixtures/content-sections/section-widget-gallery', 'Content Sections widget gallery'],
]);
