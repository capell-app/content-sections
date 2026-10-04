<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\EnsureSectionBlueprintForKeyAction;
use Capell\Core\Data\PackageData;
use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Core\Enums\PackageTypeEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Support\Packages\PackageLifecycleRunner;

it('stores Filament icon enums as complete Blade icon aliases', function (): void {
    $heroBlueprint = EnsureSectionBlueprintForKeyAction::run('hero');
    $featuresBlueprint = EnsureSectionBlueprintForKeyAction::run('features');

    expect(data_get($heroBlueprint->admin, 'icon'))
        ->toBe('heroicon-o-sparkles')
        ->and(data_get($featuresBlueprint->admin, 'icon'))
        ->toBe('heroicon-o-squares-2x2');
});

it('installs the registered section blueprints before any authoring and preserves their identities on repeated installation', function (): void {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/capell.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($manifest)) {
        throw new LogicException('The package manifest must contain an object.');
    }

    $actions = $manifest['actions'] ?? [];
    if (! is_array($actions)) {
        throw new LogicException('Package lifecycle actions must contain an object.');
    }

    $installAction = $actions['install'] ?? null;
    if ($installAction !== null && (! is_string($installAction) || ! class_exists($installAction))) {
        throw new LogicException('The install action must name an existing class.');
    }

    $package = new PackageData(
        name: 'capell-app/content-sections',
        type: PackageTypeEnum::Plugin,
        installAction: $installAction,
    );
    $pageBlueprint = Blueprint::factory()->page()->createOne(['key' => 'hero']);
    $runner = resolve(PackageLifecycleRunner::class);
    $runner->run($package, 'install', null, $package->getInstallAction());

    $ids = Blueprint::query()->where('type', 'section')->orderBy('key')->pluck('id', 'key')->all();
    expect($ids)->toHaveCount(17)->toHaveKeys(['hero', 'features', 'faq']);
    $runner->run($package, 'install', null, $package->getInstallAction());
    expect(Blueprint::query()->where('type', 'section')->orderBy('key')->pluck('id', 'key')->all())->toBe($ids)
        ->and($pageBlueprint->refresh()->getRawOriginal('type'))->toBe(BlueprintSubjectEnum::Page->value);
});
