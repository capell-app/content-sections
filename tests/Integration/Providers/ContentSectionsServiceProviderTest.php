<?php

declare(strict_types=1);

use Capell\ContentSections\Providers\ContentSectionsServiceProvider;
use Capell\ContentSections\Support\SectionRegistry;
use Capell\Core\Facades\CapellCore;

it('registers content sections as a standalone Capell package', function (): void {
    expect(CapellCore::hasPackage(ContentSectionsServiceProvider::$packageName))->toBeTrue()
        ->and(CapellCore::getPackage(ContentSectionsServiceProvider::$packageName)->serviceProviderClass)
        ->toBe(ContentSectionsServiceProvider::class);
});

it('bootstraps the section registry only once when package boot is repeated', function (): void {
    $this->app->forgetInstance(SectionRegistry::class);

    $provider = new ContentSectionsServiceProvider($this->app);
    $registerSectionRegistry = new ReflectionMethod($provider, 'registerSectionRegistry');

    $registerSectionRegistry->invoke($provider);
    $registry = $this->app->make(SectionRegistry::class);
    $registeredSections = $registry->all();

    $registerSectionRegistry->invoke($provider);

    expect($registry->all())->toBe($registeredSections);
});
