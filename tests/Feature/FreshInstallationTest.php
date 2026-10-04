<?php

declare(strict_types=1);

namespace Capell\ContentSections\Tests\Feature;

use Capell\ContentSections\Actions\InstallContentSectionsPackageAction;
use Capell\ContentSections\Providers\ContentSectionsServiceProvider;
use Capell\ContentSections\Tests\ContentSectionsTestCase;
use Capell\Core\Actions\Install\RunMigrationsAction;
use Capell\Core\Actions\InstallPackageAction;
use Capell\Core\Contracts\ProgressReporter;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Support\BlueprintSubjectRegistry;
use Capell\Core\Support\Manifest\ManifestLoader;
use Capell\Core\Support\Manifest\ManifestValidator;
use Illuminate\Support\Facades\Schema;
use Override;

final class FreshInstallationTest extends ContentSectionsTestCase
{
    public function test_it_installs_from_an_uninstalled_state_and_preserves_blueprint_identities(): void
    {
        $package = CapellCore::getPackage(ContentSectionsServiceProvider::$packageName);
        // Provider registration can retain installed Composer metadata; exercise
        // the lifecycle declared by the source manifest under review.
        $package->manifest = new ManifestLoader(new ManifestValidator)->load(dirname(__DIR__, 2) . '/capell.json');
        $this->assertSame(InstallContentSectionsPackageAction::class, $package->getInstallAction());
        $this->assertFalse($package->isInstalled());
        $this->assertNull(resolve(BlueprintSubjectRegistry::class)->descriptorOrNull('section'));
        $this->assertSame(0, Blueprint::query()->where('type', 'section')->count());
        $this->artisan('migrate', [
            '--path' => dirname(__DIR__, 2) . '/database/migrations',
            '--realpath' => true,
        ])->assertSuccessful();
        // Only migration orchestration is doubled: the isolated schema exists,
        // while the real installer still owns subject registration and lifecycle order.
        $this->assertTrue(Schema::hasTable('sections'));
        app()->instance(RunMigrationsAction::class, new readonly class
        {
            public function handle(ProgressReporter $reporter, bool $includeSettings = true, bool $includeSchema = true): void {}
        });
        $page = Blueprint::factory()->page()->createOne(['key' => 'hero']);

        InstallPackageAction::run($package);

        $this->assertTrue($package->isInstalled());
        $ids = Blueprint::query()->where('type', 'section')->orderBy('key')->pluck('id', 'key')->all();
        $this->assertCount(17, $ids);
        foreach (['hero', 'features', 'faq'] as $key) {
            $this->assertArrayHasKey($key, $ids);
        }

        InstallPackageAction::run($package);
        $this->assertSame($ids, Blueprint::query()->where('type', 'section')->orderBy('key')->pluck('id', 'key')->all());
        $this->assertSame('page', $page->refresh()->getRawOriginal('type'));
    }

    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);
        CapellCore::forcePackageInstalled('capell-app/block-library');
        CapellCore::markPackageUninstalled(ContentSectionsServiceProvider::$packageName);
    }
}
