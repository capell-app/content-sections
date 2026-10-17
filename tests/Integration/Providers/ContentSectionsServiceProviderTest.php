<?php

declare(strict_types=1);

use Capell\BlockLibrary\Support\BlockRegistry;
use Capell\ContentSections\Enums\ResourceEnum;
use Capell\ContentSections\Providers\ContentSectionsServiceProvider;
use Capell\ContentSections\Support\SectionRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeReceiptWitness;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;

it('registers content sections as a standalone Capell package', function (): void {
    expect(CapellCore::hasPackage(ContentSectionsServiceProvider::$packageName))->toBeTrue()
        ->and(CapellCore::getPackage(ContentSectionsServiceProvider::$packageName)->serviceProviderClass)
        ->toBe(ContentSectionsServiceProvider::class);
});

it('bootstraps the section registry only once when package boot is repeated', function (): void {
    app()->forgetInstance(SectionRegistry::class);

    $provider = new ContentSectionsServiceProvider(app());
    $provider->register();
    $provider->boot();
    $provider->callBootedCallbacks();
    $registry = app()->make(SectionRegistry::class);
    $registeredSections = $registry->all();

    $provider->boot();
    $provider->callBootedCallbacks();

    expect($registeredSections)->not->toBeEmpty()
        ->and($registry->all())->toBe($registeredSections)
        ->and(app()->make(SectionRegistry::class)->all())->toBe($registeredSections);
});

it('keeps content blocks resolvable after registering the provider again', function (): void {
    $blocks = app()->make(BlockRegistry::class)->all();
    expect($blocks)->toHaveKey('section.content');

    $provider = new ContentSectionsServiceProvider(app());
    $provider->register();
    $provider->boot();
    $provider->callBootedCallbacks();

    app()->forgetInstance(BlockRegistry::class);

    expect(app()->make(BlockRegistry::class)->all())->toEqual($blocks);
});

it('contributes the section resource once during installed boot', function (): void {
    InstalledRuntimeState::run(function (): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('content-sections'), true);

        expect(array_filter(
            $app->make(InstalledRuntimeReceiptWitness::class)->emissions(),
            static fn (array $emission): bool => $emission[2] === ResourceEnum::Section->value,
        ))->toHaveCount(1);
    });
});
