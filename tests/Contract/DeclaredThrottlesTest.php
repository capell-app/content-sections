<?php

declare(strict_types=1);

use Capell\Core\Testing\Assertions\AssertsDeclaredThrottles;

it('registers every throttle its manifest declares, on the routes it declares', function (): void {
    expect(fn () => AssertsDeclaredThrottles::run(dirname(__DIR__, 2) . '/capell.json'))->not->toThrow(AssertionError::class);
});
