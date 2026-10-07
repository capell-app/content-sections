<?php

declare(strict_types=1);

use Capell\ContentSections\Support\SectionPublicLayoutWidgetPayloadContributor;
use Capell\LayoutBuilder\Contracts\PublicLayoutWidgetPayloadContributor;
use Capell\Tests\Support\PackageInstallationTestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;

it('registers installed runtime once during in-process installation', function (): void {
    /** @return list<class-string<SectionPublicLayoutWidgetPayloadContributor>> */
    $surface = static function (Application $app): array {
        $contributions = [];
        foreach ($app->tagged(PublicLayoutWidgetPayloadContributor::TAG) as $contribution) {
            if ($contribution instanceof SectionPublicLayoutWidgetPayloadContributor) {
                $contributions[] = $contribution::class;
            }
        }

        return $contributions;
    };

    $fresh = [];
    PackageInstallationTestCase::assertFreshInstalledBoot('content-sections', static function (Application $app) use ($surface, &$fresh): void {
        $fresh = $surface($app);
        expect($fresh)->toBe([SectionPublicLayoutWidgetPayloadContributor::class]);
    });

    PackageInstallationTestCase::assertInProcessInstallation('content-sections', static function (Application $app, Closure $refresh) use ($surface, $fresh): void {
        expect($surface($app))->toBe([]);
        $refresh();
        expect($surface($app))->toBe($fresh);

        $schedule = $app->make(Schedule::class);
        $scheduledEvents = $schedule->events();
        $listeners = $app->make(Dispatcher::class)->getRawListeners();
        $refresh();
        expect($surface($app))->toBe($fresh)
            ->and($app->make(Dispatcher::class)->getRawListeners())->toBe($listeners)
            ->and($schedule->events())->toBe($scheduledEvents);
    });
});
