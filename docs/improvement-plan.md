# Content Sections — Improvement & Growth Plan

> Package: capell-app/content-sections · Kind: package · Tier: free · Product group: Capell Foundation · Bundle: foundation · Status: Draft

## 1. Snapshot

Content Sections ships a single Eloquent model (`Section`, nested-set, soft-deletes, translatable, media, userstamps) and a catalog of reusable, blueprint-backed page-section widgets that editors manage in a Filament `SectionResource` and that render on the public frontend via package-owned Blade components. It is a runtime + admin package (`surfaces: ["admin","frontend"]`) that bridges into `capell-app/block-library` (each registered section is exposed as a `section.{key}` block) and `capell-app/layout-builder` (public render is driven by `SectionPublicWidgetPayloadContributor`, which the layout graph calls per placed widget). Hard deps: `admin`, `block-library`, `core`, `frontend`, `layout-builder`; optional integration with `publishing-studio` (workspace clone/finalize) and `public-actions`.

**Section types actually shipped.** `DefaultSectionDefinitionProvider` registers **17** definitions: `content`, `hero`, `testimonial`, `accordion`, `call_to_action`, `comparison`, `counter`, `divider`, `faq`, `features`, `logos`, `pricing`, `stats`, `table`, `tabs`, `team`, `timeline` (`src/Support/DefaultSectionDefinitionProvider.php`). Each maps to a configurator in `SectionConfiguratorEnum` and a Blade view under `resources/views/components/section/widgets/`. `RichSectionConfigurator` (507 lines, `src/Filament/Configurators/Sections/RichSectionConfigurator.php`) is the shared base class that 14 richer configurators extend. Two frontend components (`section.widget`, `section.team-member`) are registered with the frontend component registry; the rest render through the dynamic-component fallback.

**Marketplace summary:** The manifest now leads with the 17 ready-to-use, themeable page sections and Foundation bundle positioning. **Screenshots:** the marketplace block now declares the extension card plus four Capell runner PNGs for the admin index, create form, edit form/assets panel, and dark-mode index. `docs/screenshots.json` still defines a richer **5-entry** capture contract; the 2026-06-06 seeded runner pass produced valid admin index/create/edit captures, while the selector-modal and frontend widget gallery entries still need dedicated fixture routes/states because their generated fallback PNGs were discarded.

## 2. Improvements (existing functionality)

1. **Escape public section output (single render helper) — see §4.1.** — Public Blade renders editor content with `{!! !!}` across every widget; the cleanest fix point is a sanitisation pass on the data the contributor builds rather than 20 templates. — `src/Support/SectionPublicWidgetPayloadContributor.php` (`sectionData`/`renderSection`) — effort **M**.
2. **Replace the stub health check with real surface/install assertions.** — `ContentSectionsHealthCheck` only returns `'^4.0'`; it never checks anything, yet the manifest labels it "package surfaces, providers, and install health are discoverable by Diagnostics" at `severity: critical`. Implement checks that the `Section` morph alias is registered, the `SectionResource` is contributed, the `sections` table exists, and the default-section registry is populated. — `src/Health/ContentSectionsHealthCheck.php`, `capell.json` healthChecks — effort **S**.
3. **Benchmark per-section render cost against the declared budget.** — `renderSection` now prefers named views and keeps Laravel's fallback compiled dynamic component view available between renders, but multiple sections per page can still multiply component work. Measure the fallback path against `frontendRenderBudgetMs: 20` before relying on the manifest budget. — `src/Support/SectionPublicWidgetPayloadContributor.php:128-157` — effort **S**.
4. **Decouple the cross-package `widget_assets` write in publish finalize.** — `finalizeSectionPublish` issues a raw `DB::table('widget_assets')->update(...)` against a layout-builder-owned table, bypassing models/observers and hard-coding another package's schema. Move this behind a layout-builder contract/Action (e.g. a "repoint widget asset to live section" service) so the boundary stays at public contracts (the arch test in `tests/Arch/ContentSectionsBoundaryTest.php` already polices direct `Capell\LayoutBuilder` references). — `src/Providers/ContentSectionsServiceProvider.php:450-476` — effort **M**.
5. **Move provider wiring into Actions per package convention.** — `cloneSectionIntoWorkspace` and `finalizeSectionPublish` are ~70 lines of domain logic living in the service provider. Extract to `CloneSectionIntoWorkspaceAction` / `FinalizeSectionPublishAction` and test directly. — `src/Providers/ContentSectionsServiceProvider.php:406-476` — effort **M**.
6. **Closed 2026-06-07: stale `simple-list` widget claim reconciled.** — `resources/views/components/section/widgets/simple-list.blade.php` is no longer present, and `docs/overview.md` lists only the 17 registered section widget families from `DefaultSectionDefinitionProvider`. No orphaned `simple-list` public widget remains to register or document. — `resources/views/components/section/widgets/`, `src/Support/DefaultSectionDefinitionProvider.php`, `docs/overview.md`
7. **Shipped 2026-06-06: Rename `PopularSectionConfigurator` to its actual role.** — The shared base configurator is now `RichSectionConfigurator`, and the 14 richer section configurators extend it. The class name no longer implies a "popular sections" section type. — `src/Filament/Configurators/Sections/RichSectionConfigurator.php` + subclasses — effort **S**.
8. **Localise demo content instead of hardcoded English.** — `BuildSectionDemoDataAction` returns English labels/descriptions/marketing copy directly (`'Reusable rich text content.'`, plan names, FAQ copy) and short-circuits the translation keys via `fallbackLabel`/`fallbackDescription`. Demo seeds shown in a marketplace gallery should respect locale. — `src/Actions/BuildSectionDemoDataAction.php` — effort **M**.

## 3. Missing Features (gaps)

Manifest `capabilities: []` is empty, so there is nothing to tie improvements to — that itself is gap #1: a section-catalog package must advertise capabilities (see §5). Against section-catalog norms:

- **Layout / spacing / background controls are inconsistent.** `hero` reads `meta['alignment']` and `widget.blade.php` has a `color` switch, but there is no shared, per-section background / padding / max-width / alignment contract. Editors get different controls per widget instead of a common "section appearance" block. Table-stakes for a section library.
- **No responsive / breakpoint controls in this package.** `docs/overview.md` describes breakpoint editing and layout areas, but that behaviour lives in layout-builder, not here; the section widgets themselves expose no responsive options. Differentiator opportunity: per-section responsive visibility/column overrides owned by the section.
- **Accessibility is unaudited.** `faq`/`accordion` use `<details>/<summary>` (good), but raw-HTML injection (§4) plus no heading-level config (`hero` hard-codes `<h1>`, others `<h2>`) risks duplicate-H1 and broken document outline when multiple sections render on one page. No ARIA on `tabs`/`counter`. The `section-widget-gallery` screenshot entry explicitly asks for anonymous-safe output — accessibility should ride along.
- **No anchor / ID / "jump link" support** on sections, despite FAQ/timeline/pricing being natural deep-link targets.
- **No content-block reuse surfaced to editors as variants/presets.** Sections are reusable records, but there is no notion of a saved style preset or duplicate-as-template beyond raw `replicate`.
- **Differentiator vs table-stakes:** the 17-type catalog + block-library bridge is solid table-stakes. The differentiators would be (a) a shared section-appearance/responsive contract, (b) accessible-by-default rendering with configurable heading levels and anchors, (c) safe rich-text handling — none of which exist yet.

## 4. Issues / Risks

### 4.1 Stored-XSS in public output (CRITICAL — public-output safety)

Editor-controlled content is rendered as raw HTML on the anonymous frontend with no sanitisation:

- `{!! $summary !!}` appears in `content`, `hero`, `faq`, `call-to-action`, `pricing`, `logos`, `counter`, `stats`, `team`, `tabs`, `accordion`, `timeline`, and `features` views (`resources/views/components/section/widgets/*.blade.php`), with public safety now enforced at the payload contributor boundary.
- Nested meta is also raw in package views after sanitised payload assembly: `{!! $question['answer'] !!}` (`faq.blade.php:35`), `{!! $item['content'] !!}` (`accordion.blade.php:44`), and `{!! $tab['content'] !!}` (`tabs.blade.php:48`).
- The source data is unsanitised: `SectionPublicWidgetPayloadContributor::renderSection` wraps summary in `new HtmlString((string) $data['summary'])` (line 139), and `meta` flows straight from `Section->meta` JSON via `metaFor()` with only `array_replace_recursive`. There is no HTML Purifier / allow-list anywhere in the package.
- Risk: any actor who can edit a section's translation `content`/`summary` or `meta` (a non-super editor with `update` permission, or any upstream package that writes section meta) can persist `<script>`/event-handler HTML that executes for every anonymous visitor. This directly violates the public-output-safety rule. Fix at the contributor boundary (§2.1) by passing section content through a shared sanitiser before it reaches `HtmlString`/the views.

### 4.2 Unvalidated icon strings into `svg()` (render safety)

`features.blade.php:29` and `counter.blade.php:30` do `{!! svg($feature['icon'], ...)->toHtml() !!}` where `$feature['icon']` / `$counter['icon']` is an editor-supplied meta string. Confirm `svg()` cannot resolve arbitrary filesystem paths or emit attacker-controlled markup; at minimum validate against a known icon allow-list.

### 4.3 Test gaps (no proof of public safety)

- **Shipped 2026-06-07: public-output safety coverage now includes authenticated non-admin visitors.** Sanitisation tests assert anonymous payload/html safety and a normal signed-in frontend visitor receives the same safe public graph without scripts, authoring markers, signed editor URLs, or package internals. — `tests/Feature/SectionPublicOutputSanitisationTest.php`
- The health check is only asserted to _exist_ (`tests/Unit/ContentSectionsCoverageTest.php`), with no behavioural coverage — unsurprising, since it has no behaviour.
- No test covers the `finalizeSectionPublish` `widget_assets` repoint or the workspace clone path beyond the publishing feature test. The capell skill requires rendering/cache changes to ship with anonymous + non-admin safety tests; this package does not yet meet that bar.

### 4.4 Cache safety vs declared budget

**Closed 2026-06-06:** `capell.json` now declares `content-sections` cache tags and `Section` invalidation sources for created, updated, deleted, restored, and force-deleted events. Blueprint and linked-page URL dependencies remain broader cache-depth follow-up, but the stale `queueInvalidation`/empty-source mismatch is closed for the package-owned section model.

### 4.5 Performance budget realism

`frontendRenderBudgetMs: 20` is optimistic given `Section::getMorphRelations()` eager-loads `ancestors.blueprint`, `media`, `image`, `linkedPage.translation/pageUrl.siteDomain`, `translation`, and `blueprint` per section. Multiple sections in one widget container can multiply this. Benchmark against the budget; the public Blade itself correctly avoids DB queries (relations are pre-hydrated and guarded with `relationLoaded`), which is good.

### 4.6 i18n

Translation files exist (`resources/lang/en/*`), but `BuildSectionDemoDataAction` bypasses them with hardcoded English (§2.8), and several blades emit literal English only via `__()` keys that may be unmapped. Verify every `capell-content-sections::section.{key}.label/description` key is defined, since `DefaultSectionDefinitionProvider` references all 17.

### 4.7 Manifest / docs accuracy (tech debt)

- `contributes: []` while the provider contributes an admin resource, ~18 configurators, a `section` page type, an asset, two frontend components, and the public widget contributor; `contributionTraceability.deferredContributions` lists `admin-resource, asset, configurator, model, page-type, route, schema-extender` — none declared in `contributes[]`.
- `permissions: []` while `SectionPolicy` relies on Shield permissions (`view_any`, `view`, `create`, `update`, `delete`, `restore`, `force_delete`, `replicate`, `reorder`).
- `surfaces` mismatch: `capell.json` = `["admin","frontend"]`; `README.md` = "Filament admin, Livewire, database" (drops frontend, adds Livewire/database).
- `database.requiredTables: []` though the package owns and requires the `sections` table.
- **Closed 2026-06-07: stale Layout Builder mutation docs were removed.** `docs/mutations.md` has been deleted because `src/Actions/Mutations`, `ContentSectionsStateData`, `NormalizeContentSectionsStateAction`, and `LayoutMutationResultData` are not Content Sections APIs. `docs/overview.md` now describes the actual reusable-section editing workflow and explicitly leaves layout modes, breakpoints, and undo/redo to `capell-app/layout-builder`.

## 5. Marketplace & Positioning

**Role.** Free, foundation-bundled, first-party — correct. This package is platform glue: it is what makes a Capell site able to assemble pages from reusable, themeable content blocks, and it is the producer side of the block-library/layout-builder pairing. That makes it a keystone of the foundation pitch ("compose pages from reusable sections, no markup in content fields"), so its marketing should sell the _catalog_ and _reuse_, not describe itself tautologically.

**Current `summary` / `description` critique.** The marketplace summary now names the 17-section catalog, reuse workflow, safe Blade rendering, and Foundation bundle fit. Composer copy is still shorter than the ideal positioning below and can be tightened when the screenshot fixture work lands.

**Improved summary (marketplace):** "Seventeen ready-to-use, themeable page sections — hero, FAQ, pricing, stats, testimonials, team, comparison, timeline and more — that editors reuse across pages and developers render through safe, package-owned Blade. Bundled free with Capell Foundation."

**Improved composer `description`:** "A free catalog of 17 reusable, blueprint-backed page sections (hero, FAQ, pricing, stats, team, comparison, timeline, and more) for Capell, rendered through layout-builder and block-library."

**Free/bundle vs premium upsell.** Keep the catalog free — it is foundation table-stakes and drives adoption of the paid layout/publishing tooling. The natural premium upsell line is _advanced_ section types and capabilities (A/B-testable sections, personalised/segmented sections, form/CRM sections, animation presets), which the demo copy already hints at ("Advanced widgets stay in optional packages", "Pro bundle: 0"). Position this package as the on-ramp and reserve those as paid add-on packages.

**Screenshot / media gaps.** The package now promotes real Capell runner admin captures for the section index, create form, edit form/assets panel, and dark-mode index. The remaining highest-leverage media gap is the visual catalog itself: add dedicated selector-modal and anonymous frontend widget-gallery fixture routes/states so the remaining `docs/screenshots.json` entries do not fall back to Dashboard or the generic demo page. A per-section thumbnail strip would be the single highest-leverage media improvement.

**Suggested keywords/tags (8–12):** `page-sections`, `content-blocks`, `reusable-content`, `hero`, `pricing-section`, `faq`, `testimonials`, `cms-sections`, `block-library`, `layout-builder`, `filament`, `foundation`.

## 6. Prioritized Roadmap

| Item                                                                                     | Bucket | Effort | Impact   | Section ref |
| ---------------------------------------------------------------------------------------- | ------ | ------ | -------- | ----------- |
| Shipped 2026-06-04: sanitise editor HTML at the contributor boundary (kill stored-XSS)   | Done   | M      | Critical | §2.1 / §4.1 |
| Shipped 2026-06-04: add anonymous public-output sanitisation tests                       | Done   | M      | Critical | §4.3        |
| Shipped 2026-06-04: implement real `ContentSectionsHealthCheck`                          | Done   | S      | High     | §2.2 / §4.3 |
| Shipped 2026-06-04: validate `svg()` icon meta against an allow-list                     | Done   | S      | High     | §4.2        |
| Shipped 2026-06-04: fix manifest accuracy: `contributes`, permissions, tables, surfaces  | Done   | S      | Med      | §4.7        |
| Benchmark per-section render cost against the declared budget                            | Next   | S      | High     | §2.3 / §4.5 |
| Declare real `cacheSafety.invalidationSources` / register cache dependency               | Done   | S      | Med      | §4.4        |
| Shipped 2026-06-07: Add explicit non-admin public-safety assertions for authenticated frontend visitors | Done   | S      | High     | §4.3        |
| Closed 2026-06-07: Remove or wire orphaned `simple-list` widget (and the overview doc line) | Done   | S      | Med      | §2.6        |
| Extract workspace clone + publish-finalize into Actions behind a layout-builder contract | Next   | M      | Med      | §2.4 / §2.5 |
| Add selector-modal and frontend widget-gallery fixture routes; promote remaining shots   | Next   | M      | High     | §5          |
| Shipped 2026-06-07: Fix/relocate stale `docs/mutations.md` + overview editor-workflow bleed | Done   | S      | Med      | §4.7        |
| Add shared section-appearance contract (background/spacing/alignment/heading level)      | Later  | L      | High     | §3          |
| Add per-section anchor IDs + accessible defaults (ARIA, configurable headings)           | Later  | M      | Med      | §3          |
| Localise `BuildSectionDemoDataAction` demo content                                       | Later  | M      | Low      | §2.8 / §4.6 |
| Shipped 2026-06-06: Rename `PopularSectionConfigurator` → `RichSectionConfigurator` | Done   | S      | Low      | §2.7        |
