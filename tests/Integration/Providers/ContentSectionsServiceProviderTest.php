<?php

declare(strict_types=1);

use Capell\ContentSections\Providers\ContentSectionsServiceProvider;
use Capell\ContentSections\Tests\ContentSectionsTestCase;
use Capell\Core\Facades\CapellCore;

uses(ContentSectionsTestCase::class);

it('registers content sections as a standalone Capell package', function (): void {
    expect(CapellCore::hasPackage(ContentSectionsServiceProvider::$packageName))->toBeTrue()
        ->and(CapellCore::getPackage(ContentSectionsServiceProvider::$packageName)->serviceProviderClass)
        ->toBe(ContentSectionsServiceProvider::class);
});
