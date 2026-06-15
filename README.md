# Content Sections

<!-- prettier-ignore-start -->

## What This Plugin Adds

Content Sections is an **Available**, **Schema-owning** Capell package in the **Capell Foundation** product group. It ships as `capell-app/content-sections` and extends these surfaces: admin, frontend.

Seventeen ready-to-use, themeable page sections - hero, FAQ, pricing, stats, testimonials, team, comparison, timeline and more - that editors reuse across pages and developers render through safe, package-owned Blade. Bundled free with Capell Foundation.

After install, admins get package-owned management surfaces and public users may see package-owned frontend output or routes.

Status details:

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/content-sections`
- Namespace: `Capell\ContentSections`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, models, Laravel routes, Filament classes, and Blade views instead of pushing this behaviour into core or application code.

**For teams:** Seventeen ready-to-use, themeable page sections - hero, FAQ, pricing, stats, testimonials, team, comparison, timeline and more - that editors reuse across pages and developers render through safe, package-owned Blade. Bundled free with Capell Foundation.

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

- Reusable sections index (admin, required).
- Create reusable section form (admin, required).
- Edit reusable section with assets (admin, required).
- Section selector modal (frontend, required).
- Frontend section widget gallery (frontend, required).

## Technical Shape

- Service providers: `Capell\ContentSections\Providers\ContentSectionsServiceProvider`.
- Config files: `packages/content-sections/config/capell-content-sections.php`.
- Migrations: `packages/content-sections/database/migrations/2026_05_10_190844_01_create_sections_table.php`.
- Models: `ComposhipsJsonRelationshipsTrait`, `Section`.
- Filament classes: `CreateContentAction`, `ActionsRepeater`, `AssetsRepeater`, `BlueprintSelect`, `DetailsSchema`, `RelatedRepeater`, `SettingsSchema`, `TranslationsRepeater`, `ContentSelect`, `CustomColorInput`, `ContentNameColumn`, `HasAssetsRelationManager`, `and 28 more`.
- Livewire components: `AbstractAssets`, `SectionAssets`, `ModalTableSelect`.
- Route files: `packages/content-sections/routes/web.php`.
- Policies: `SectionPolicy`.
- Actions: `BuildSectionAssetRenderDataAction`, `BuildSectionDemoDataAction`, `CancelScheduledSectionUnpublishAction`, `CloneSectionIntoWorkspaceAction`, `CreateContentAction`, `CreateHeroContentBlueprintAction`, `EnsureSectionBlueprintForKeyAction`, `FinalizeSectionPublishAction`, `ModifyContentSelectCreateAction`, `MutateContentDataBeforeFillAction`, `NormalizeSectionIconAction`, `RegisterDefaultSectionsAction`, `and 6 more`.
- Data objects: `SectionAssetRenderData`, `SectionDefinitionData`, `SectionVisibilityActionResultData`.
- Manifest contributions: `admin-resource: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`, `asset: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`, `configurator: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`, `frontend-component: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`, `model: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`, `page-type: Capell\ContentSections\Manifest\ContentSectionsPackageContribution`, `schema-extender: Capell\ContentSections\Manifest\ContentSectionsSchemaExtendersContribution`.
- Health checks: `Capell\ContentSections\Health\ContentSectionsHealthCheck`.
- Blade views: `packages/content-sections/resources/views/components/section/asset.blade.php`, `packages/content-sections/resources/views/components/section/team-member.blade.php`, `packages/content-sections/resources/views/components/section/widget.blade.php`, `packages/content-sections/resources/views/livewire/filament/widgets-table-select.blade.php`, `packages/content-sections/resources/views/screenshots/section-selector-modal.blade.php`, `packages/content-sections/resources/views/screenshots/section-widget-gallery.blade.php`, `packages/content-sections/resources/views/section/demo.blade.php`.
- Cache tags: `content-sections`.

## Data Model

- Required tables: `sections`.
- Models: `ComposhipsJsonRelationshipsTrait`, `Section`.
- Migration files: `2026_05_10_190844_01_create_sections_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: Docs gap unless the package has an explicit pruning command, retention setting, or tested cascade path.

## Install Impact

- Admin navigation: adds package-owned Filament classes when registered.
- Permissions: `ViewAny:Section`, `View:Section`, `Create:Section`, `Update:Section`, `Delete:Section`, `DeleteAny:Section`, `Restore:Section`, `RestoreAny:Section`, `ForceDelete:Section`, `ForceDeleteAny:Section`, `Replicate:Section`, `Reorder:Section`.
- Public routes: route files exist and must be reviewed before public enablement.
- Database changes: package migrations are declared.
- Settings: no package settings declared.
- Queues or schedules: none detected in standard package paths.
- Cache tags: `content-sections`.
- Commands: none declared.

## Common Pitfalls

- Run migrations before opening package resources or public routes.
- Review route middleware, throttling, signed URLs, and public-output safety before exposing routes.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Route returns unexpected output | Route cache, middleware, or signed URL setup does not match the package route file | Check the route files listed in `Technical Shape` | Clear route cache and verify middleware before exposing public routes |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/content-sections`.
2. Run the required setup: `php artisan migrate`.
3. Open the related Capell admin surface and verify Content Sections appears.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Block Library](../block-library/README.md), [Layout Builder](../layout-builder/README.md), [Publishing Studio](../publishing-studio/README.md), [Public Actions](../public-actions/README.md).
- Focused tests: `vendor/bin/pest packages/content-sections/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
