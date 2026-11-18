# Content Sections reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/content-sections`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Content Sections is an **Available** Capell package in the **Capell Essentials** product group. It ships as `capell-app/content-sections` and extends these surfaces: admin, frontend.

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/content-sections`
- Namespace: `Capell\ContentSections`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\ContentSections\Providers\MigrationMetadataServiceProvider`
- `Capell\ContentSections\Providers\ContentSectionsServiceProvider`

### Config files

- `packages/content-sections/config/capell-content-sections.php`

### Migrations

- `packages/content-sections/database/migrations/2026_05_10_190844_01_create_sections_table.php`
- `packages/content-sections/database/migrations/2026_08_28_000003_change_section_visibility_to_datetime.php`

### Models

- `ComposhipsJsonRelationshipsTrait`
- `SectionNestedSet`
- `Section`

### Filament classes

- `CreateContentAction`
- `ActionsRepeater`
- `AssetsRepeater`
- `BlueprintSelect`
- `DetailsSchema`
- `RelatedRepeater`
- `SettingsSchema`
- `TranslationsRepeater`
- `ContentSelect`
- `CustomColorInput`
- `SectionMediaFields`
- `ContentNameColumn`
- `HasAssetsRelationManager`
- `ContentBlueprintConfigurator`
- `AccordionSectionConfigurator`
- `CallToActionSectionConfigurator`
- `ComparisonSectionConfigurator`
- `CounterSectionConfigurator`
- `DefaultSectionConfigurator`
- `DividerSectionConfigurator`
- `FaqSectionConfigurator`
- `FeaturesSectionConfigurator`
- `HeroSectionConfigurator`
- `LogosSectionConfigurator`
- `PricingSectionConfigurator`
- `RichSectionConfigurator`
- `StatsSectionConfigurator`
- `TableSectionConfigurator`
- `TabsSectionConfigurator`
- `TeamSectionConfigurator`
- `TestimonialSectionConfigurator`
- `TimelineSectionConfigurator`
- `CreateSection`
- `EditSection`
- `ListSections`
- `SectionAssetsRelationManager`
- `SectionForm`
- `SectionResource`
- `SectionSelectionTable`
- `SectionsTable`
- `SectionAlertsWidget`

### Livewire components

- `AbstractAssets`
- `SectionAssets`
- `ModalTableSelect`

### Route files

- `packages/content-sections/routes/web.php`

### Policies

- `SectionAssetAttachmentPolicy`
- `SectionPolicy`

### Extension contracts

- `SectionDefinitionProvider`

### Actions

- `BuildSectionAssetRenderDataAction`
- `BuildSectionCreateFormDataAction`
- `BuildSectionDemoDataAction`
- `BuildSectionRevisionSnapshotAction`
- `BuildSectionUsageSummaryAction`
- `CancelScheduledSectionUnpublishAction`
- `CloneSectionIntoWorkspaceAction`
- `CopySectionMediaAction`
- `CreateHeroContentBlueprintAction`
- `CreateSectionContentAction`
- `EnsureSectionBlueprintForKeyAction`
- `FinalizeSectionPublishAction`
- `InvalidateSectionFragmentsForMediaAction`
- `RenderPublicSectionFragmentAction`
- `GetDefaultLanguageIdAction`
- `HydrateSectionMediaSlotsAction`
- `InstallContentSectionsPackageAction`
- `InvalidateDueScheduledSectionCachesAction`
- `MaterialisePendingRelatedCopiesAction`
- `ModifyContentSelectCreateAction`
- `NormalizeSectionIconAction`
- `PreparePublicSectionMetaAction`
- `RegisterDefaultSectionsAction`
- `RegisterSectionBlueprintSubjectsAction`
- `RegisterSectionDefinitionProviderAction`
- `ReplicateContentAction`
- `ResolveRequestedSectionBlueprintAction`
- `ResolveSectionComponentAction`
- `ResolveSectionMediaAction`
- `RestoreSectionRevisionAction`
- `SanitizeSectionHtmlAction`
- `UnpublishSectionAction`

### Data objects

- `PublicSectionFragmentResultData`
- `SectionAssetRenderData`
- `SectionDefinitionData`
- `SectionMediaData`
- `SectionPublicRenderData`
- `SectionUsageDestinationData`
- `SectionUsageSummaryData`
- `SectionVisibilityActionResultData`

### Command signatures

- `content-sections:invalidate-due-caches`

### Manifest action API

- `install: Capell\ContentSections\Actions\InstallContentSectionsPackageAction`

### Scheduled commands

- `content-sections:invalidate-due-caches (everyMinute; package registered)`

### Console command classes

- `InvalidateDueScheduledSectionCachesCommand`

### Manifest contributions

- `admin-resource: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`
- `asset: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`
- `configurator: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`
- `console-command: Capell\ContentSections\Manifest\ContentSectionsConsoleCommandsContribution`
- `frontend-component: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`
- `model: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`
- `page-type: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`
- `route: Capell\ContentSections\Manifest\ContentSectionsRoutesContribution`
- `route: Capell\ContentSections\Manifest\PublicSectionFragmentsRoutesContribution`
- `scheduled-job: Capell\ContentSections\Manifest\ContentSectionsScheduledVisibilityContribution`
- `schema-extender: Capell\ContentSections\Manifest\ContentSectionsSchemaExtendersContribution`

### Health checks

- `Capell\ContentSections\Health\ContentSectionsHealthCheck`

### Blade views

- `packages/content-sections/resources/views/components/section/asset.blade.php`
- `packages/content-sections/resources/views/components/section/team-member.blade.php`
- `packages/content-sections/resources/views/components/section/widget.blade.php`
- `packages/content-sections/resources/views/filament/sections/usage-breakdown.blade.php`
- `packages/content-sections/resources/views/livewire/filament/widgets-table-select.blade.php`
- `packages/content-sections/resources/views/screenshots/section-selector-modal.blade.php`
- `packages/content-sections/resources/views/screenshots/section-widget-gallery.blade.php`
- `packages/content-sections/resources/views/section/demo.blade.php`
- `packages/content-sections/resources/views/tables/columns/children-badge.blade.php`

### Cache tags

- `content-sections`


## Data Model

- Required tables: `sections`.
- Models: `ComposhipsJsonRelationshipsTrait`, `SectionNestedSet`, `Section`.
- Core record references in migrations: `sites via site_id`.
- Migration files: `2026_05_10_190844_01_create_sections_table.php`, `2026_08_28_000003_change_section_visibility_to_datetime.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; no timed pruning or retention schedule is declared in `capell.json`.

<!-- prettier-ignore-end -->
