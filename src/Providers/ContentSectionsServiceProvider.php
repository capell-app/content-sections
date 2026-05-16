<?php

declare(strict_types=1);

namespace Capell\ContentSections\Providers;

use BackedEnum;
use Capell\Admin\Data\AdminAssetData;
use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Enums\ConfiguratorTypeEnum as AdminConfiguratorTypeEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\ContentBlocks\Contracts\BlockDefinitionProvider;
use Capell\ContentSections\Actions\RegisterDefaultSectionsAction;
use Capell\ContentSections\Actions\RegisterSectionDefinitionProviderAction;
use Capell\ContentSections\Contracts\SectionDefinitionProvider;
use Capell\ContentSections\Enums\AssetEnum;
use Capell\ContentSections\Enums\FrontendComponentKeyEnum;
use Capell\ContentSections\Enums\LayoutTypeEnum;
use Capell\ContentSections\Enums\LivewireComponentsEnum;
use Capell\ContentSections\Enums\ResourceEnum;
use Capell\ContentSections\Filament\Configurators\Blueprints\ContentBlueprintConfigurator;
use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Support\ContentSectionsBlockDefinitionProvider;
use Capell\ContentSections\Support\ContentSectionsModelRegistrar;
use Capell\ContentSections\Support\SectionPublicElementPayloadContributor;
use Capell\ContentSections\Support\SectionRegistry;
use Capell\Core\Actions\RegisterBlazeOptimizedViewsAction;
use Capell\Core\Data\AssetData;
use Capell\Core\Data\PageTypeData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Site;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Frontend\Contracts\AssetsRegistryInterface;
use Capell\Frontend\Contracts\FrontendComponentRegistryInterface;
use Capell\Frontend\Data\FrontendAssetData;
use Capell\LayoutBuilder\Contracts\PublicElementPayloadContributor;
use Capell\PublishingStudio\WorkspaceRegistry;
use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;

class ContentSectionsServiceProvider extends AbstractPackageServiceProvider
{
    private const ADMIN_CREATED_MODEL_ACTION = 'Capell\\Admin\\Actions\\CreatedModelAction';

    private const ADMIN_DELETED_MODEL_ACTION = 'Capell\\Admin\\Actions\\DeletedModelAction';

    private const BLOCK_DEFINITION_PROVIDER = BlockDefinitionProvider::class;

    private const PUBLIC_ELEMENT_PAYLOAD_CONTRIBUTOR = PublicElementPayloadContributor::class;

    public static string $name = 'capell-content-sections';

    public static string $packageName = 'capell-app/content-sections';

    public function packageRegistered(): void
    {
        if (! interface_exists(self::BLOCK_DEFINITION_PROVIDER)) {
            return;
        }

        $this->app->tag(
            [ContentSectionsBlockDefinitionProvider::class],
            self::BLOCK_DEFINITION_PROVIDER::TAG,
        );
    }

    public function configurePackage(Package $package): void
    {
        $package->name(self::$name)
            ->hasConfigFile()
            ->hasViews(self::$name)
            ->hasTranslations();
    }

    public function registeringPackage(): void
    {
        $this->app->booting(function (): void {
            if ($this->isPackageInstalled()) {
                $this->registerResources();
            }
        });

        $this->app->booted(function (): void {
            $this->registerLivewireComponents();

            if (! $this->isPackageInstalled()) {
                return;
            }

            $this->bootInstalledPackage();
        });
    }

    private function isPackageInstalled(): bool
    {
        return CapellCore::getPackage(static::$packageName)->isInstalled();
    }

    private function bootInstalledPackage(): self
    {
        return $this
            ->registerModels()
            ->registerSectionRegistry()
            ->registerRelationships()
            ->registerResources()
            ->registerConfigurators()
            ->registerPageTypes()
            ->registerAssets()
            ->registerPublicElementPayloadContributor()
            ->registerFrontendComponents()
            ->registerEvents()
            ->registerBladeComponents()
            ->registerBlazeComponents()
            ->registerPublishingStudio();
    }

    private function registerModels(): self
    {
        ContentSectionsModelRegistrar::register();

        return $this;
    }

    private function registerSectionRegistry(): self
    {
        $this->app->singleton(SectionRegistry::class);

        $this->callAfterResolving(SectionRegistry::class, function (SectionRegistry $registry): void {
            RegisterDefaultSectionsAction::run($registry);

            foreach ($this->app->tagged(SectionDefinitionProvider::TAG) as $provider) {
                if (! $provider instanceof SectionDefinitionProvider) {
                    continue;
                }

                RegisterSectionDefinitionProviderAction::run($registry, $provider);
            }
        });

        return $this;
    }

    private function registerRelationships(): self
    {
        Site::resolveRelationUsing(
            'sections',
            fn (Site $model): HasMany => $model->hasMany(Section::class, 'site_id'),
        );

        Blueprint::resolveRelationUsing(
            'sections',
            fn (Blueprint $model): HasMany => $model->hasMany(Section::class, 'blueprint_id'),
        );

        return $this;
    }

    private function registerResources(): self
    {
        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::resource(
            class: ResourceEnum::Section->value,
            group: ResourceEnum::Section->name,
        ));

        return $this;
    }

    private function registerConfigurators(): self
    {
        foreach (resolve(SectionRegistry::class)->all() as $definition) {
            CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
                class: $definition->configurator,
                group: $definition->configuratorType instanceof BackedEnum
                    ? (string) $definition->configuratorType->value
                    : $definition->configuratorType->getName(),
                name: $definition->configurator::getKey(),
            ));
        }

        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
            class: ContentBlueprintConfigurator::class,
            group: AdminConfiguratorTypeEnum::Blueprint->value,
            name: ContentBlueprintConfigurator::getKey(),
        ));

        return $this;
    }

    private function registerPageTypes(): self
    {
        foreach (LayoutTypeEnum::cases() as $layoutType) {
            CapellCore::registerPageType(
                new PageTypeData(
                    name: $layoutType->value,
                    model: $layoutType->getModel(),
                    label: $layoutType->getLabel(),
                ),
            );
        }

        return $this;
    }

    private function registerAssets(): self
    {
        $sectionAsset = AssetEnum::Section;

        CapellCore::registerAsset(
            new AssetData(
                name: $sectionAsset->name,
                model: $sectionAsset->getModel(),
                icon: $sectionAsset->getIcon(),
                hasTranslations: $sectionAsset->hasTranslations(),
            ),
        );

        CapellAdmin::registerAsset(
            $sectionAsset,
            new AdminAssetData(
                formClass: $sectionAsset->getFormClass(),
                createAction: $sectionAsset->getCreateActionClass(),
                defaultDataAction: $sectionAsset->getDefaultDataActionClass(),
            ),
        );

        $this->callAfterResolving(AssetsRegistryInterface::class, function (AssetsRegistryInterface $assets) use ($sectionAsset): void {
            $assets->registerAsset(
                $sectionAsset,
                new FrontendAssetData(
                    component: $sectionAsset->getComponent(),
                ),
            );
        });

        return $this;
    }

    private function registerPublicElementPayloadContributor(): self
    {
        if (interface_exists(self::PUBLIC_ELEMENT_PAYLOAD_CONTRIBUTOR)) {
            $this->app->tag([SectionPublicElementPayloadContributor::class], self::PUBLIC_ELEMENT_PAYLOAD_CONTRIBUTOR::TAG);
        }

        return $this;
    }

    private function registerFrontendComponents(): self
    {
        $this->callAfterResolving(FrontendComponentRegistryInterface::class, function (FrontendComponentRegistryInterface $registry): void {
            $registry
                ->register(
                    key: FrontendComponentKeyEnum::SectionBlock->value,
                    component: 'capell-content-sections::section.block',
                    aliases: ['capell-content-sections::section.block'],
                    props: [
                        'asset',
                        'class',
                        'color',
                        'icon',
                        'image',
                        'linkText',
                        'loop',
                        'meta',
                        'size',
                        'summary',
                        'tags',
                        'title',
                        'url',
                    ],
                )
                ->register(
                    key: FrontendComponentKeyEnum::SectionTeamMember->value,
                    component: 'capell-content-sections::section.team-member',
                    aliases: ['capell-content-sections::section.team-member'],
                    props: [
                        'asset',
                        'class',
                        'color',
                        'icon',
                        'image',
                        'linkText',
                        'loop',
                        'meta',
                        'size',
                        'summary',
                        'title',
                        'url',
                    ],
                );
        });

        return $this;
    }

    private function registerEvents(): self
    {
        Section::created(function (Section $section): void {
            $action = self::ADMIN_CREATED_MODEL_ACTION;

            if (class_exists($action)) {
                $action::run($section);
            }
        });

        Section::deleted(function (Section $section): void {
            $action = self::ADMIN_DELETED_MODEL_ACTION;

            if (class_exists($action)) {
                $action::run($section);
            }
        });

        return $this;
    }

    private function registerLivewireComponents(): self
    {
        if (! $this->isLivewireV3()) {
            Livewire::addNamespace(
                namespace: 'capell-content-sections',
                classNamespace: 'Capell\\ContentSections\\Livewire',
                classPath: __DIR__ . '/../Livewire',
                classViewPath: __DIR__ . '/../../resources/views/livewire',
            );

            return $this;
        }

        foreach (LivewireComponentsEnum::getComponents() as $name => $component) {
            if (! $component) {
                continue;
            }

            Livewire::component($name, $component);
        }

        return $this;
    }

    private function isLivewireV3(): bool
    {
        $version = InstalledVersions::getVersion('livewire/livewire');

        return version_compare($version, '4.0.0', '<');
    }

    private function registerBladeComponents(): self
    {
        Blade::componentNamespace('Capell\\ContentSections\\View\\Components', 'capell-content-sections');
        Blade::anonymousComponentNamespace('Capell\\ContentSections\\View\\Components');

        return $this;
    }

    private function registerBlazeComponents(): self
    {
        RegisterBlazeOptimizedViewsAction::run(__DIR__ . '/../../resources/views/components');

        return $this;
    }

    private function registerPublishingStudio(): self
    {
        if (! class_exists(WorkspaceRegistry::class)) {
            return $this;
        }

        WorkspaceRegistry::register(Section::class);

        return $this;
    }
}
