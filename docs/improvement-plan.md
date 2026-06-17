# Content Sections — Improvement & Growth Plan

> Package: capell-app/content-sections · Kind: package · Tier: free · Product group: Capell Foundation · Bundle: foundation · Status: Draft

## 1. Snapshot

Content Sections ships a single Eloquent model (`Section`, nested-set, soft-deletes, translatable, media, userstamps) and a catalog of reusable, blueprint-backed page-section widgets that editors manage in a Filament `SectionResource` and that render on the public frontend via package-owned Blade components. It is a runtime + admin package (`surfaces: ["admin","frontend"]`) that bridges into `capell-app/block-library` (each registered section is exposed as a `section.{key}` block) and `capell-app/layout-builder` (public render is driven by `SectionPublicWidgetPayloadContributor`, which the layout graph calls per placed widget). Hard deps: `admin`, `block-library`, `core`, `frontend`, `layout-builder`; optional integration with `publishing-studio` (workspace clone/finalize) and `public-actions`.

**Section types actually shipped.** `DefaultSectionDefinitionProvider` registers **17** definitions: `content`, `hero`, `testimonial`, `accordion`, `call_to_action`, `comparison`, `counter`, `divider`, `faq`, `features`, `logos`, `pricing`, `stats`, `table`, `tabs`, `team`, `timeline` (`src/Support/DefaultSectionDefinitionProvider.php`). Each maps to a configurator in `SectionConfiguratorEnum` and a Blade view under `resources/views/components/section/widgets/`. `RichSectionConfigurator` (507 lines, `src/Filament/Configurators/Sections/RichSectionConfigurator.php`) is the shared base class that 14 richer configurators extend. Two frontend components (`section.widget`, `section.team-member`) are registered with the frontend component registry; the rest render through the dynamic-component fallback.

**Marketplace summary:** The manifest now leads with the 17 ready-to-use, themeable page sections and Foundation bundle positioning. **Screenshots:** the marketplace block now declares the extension card plus four Capell runner PNGs for the admin index, create form, edit form/assets panel, and dark-mode index. `docs/screenshots.json` defines a richer **5-entry** capture contract; the selector-modal and frontend widget gallery entries now point at dedicated route-backed fixtures so runner captures do not fall back to Dashboard or the generic demo page.

## 2. Improvements (existing functionality)

0. **Closed 2026-06-15: clear stale manifest traceability.** — The manifest now declares the screenshot fixture route contribution through a route marker class, keeps coverage for package-owned admin resource/configurator/asset/model/page-type/frontend component metadata, and declares the section schema-extender tag consumed by section configurators. — `capell.json`, `src/Manifest/ContentSectionsRoutesContribution.php`, `src/Manifest/ContentSectionsSchemaExtendersContribution.php`, `tests/Unit/ContentSectionsCoverageTest.php`
1. **Escape public section output (single render helper) — see §4.1.** — Public Blade renders editor content with `{!! !!}` across every widget; the cleanest fix point is a sanitisation pass on the data the contributor builds rather than 20 templates. — `src/Support/SectionPublicWidgetPayloadContributor.php` (`sectionData`/`renderSection`) — effort **M**.
2. **Replace the stub health check with real surface/install assertions.** — `ContentSectionsHealthCheck` only returns `'^4.0'`; it never checks anything, yet the manifest labels it "package surfaces, providers, and install health are discoverable by Diagnostics" at `severity: critical`. Implement checks that the `Section` morph alias is registered, the `SectionResource` is contributed, the `sections` table exists, and the default-section registry is populated. — `src/Health/ContentSectionsHealthCheck.php`, `capell.json` healthChecks — effort **S**.
3. **Shipped 2026-06-07: reduce duplicated per-section render work before relying on the declared budget.** — `SectionPublicWidgetPayloadContributor` now caches the per-widget section payload for the current contributor instance, so `data()` and `html()` reuse the same rendered section fragments instead of rebuilding and rerendering every section twice when `includeHtml` is enabled. Coverage asserts the top-level widget HTML matches the cached section payload HTML. — `src/Support/SectionPublicWidgetPayloadContributor.php`, `tests/Feature/PublicLayoutGraphSectionPayloadTest.php`
4. **Shipped 2026-06-07: decouple the cross-package `widget_assets` write in publish finalize.** — Layout Builder now owns `RepointWidgetAssetReferencesAction`, and Content Sections calls that Action from `FinalizeSectionPublishAction` instead of issuing a raw `DB::table('widget_assets')->update(...)` from its service provider. — `packages/layout-builder/src/Actions/RepointWidgetAssetReferencesAction.php`, `src/Actions/FinalizeSectionPublishAction.php`
5. **Shipped 2026-06-07: move provider publishing wiring into Actions per package convention.** — `ContentSectionsServiceProvider` now registers Publishing Studio callbacks that delegate to `CloneSectionIntoWorkspaceAction` and `FinalizeSectionPublishAction`; direct publishing tests cover clone and finalize behavior. — `src/Actions/CloneSectionIntoWorkspaceAction.php`, `src/Actions/FinalizeSectionPublishAction.php`, `tests/Feature/Publishing/SectionWorkspacePublishTest.php`
6. **Closed 2026-06-07: stale `simple-list` widget claim reconciled.** — `resources/views/components/section/widgets/simple-list.blade.php` is no longer present, and `docs/overview.md` lists only the 17 registered section widget families from `DefaultSectionDefinitionProvider`. No orphaned `simple-list` public widget remains to register or document. — `resources/views/components/section/widgets/`, `src/Support/DefaultSectionDefinitionProvider.php`, `docs/overview.md`
7. **Shipped 2026-06-06: Rename `PopularSectionConfigurator` to its actual role.** — The shared base configurator is now `RichSectionConfigurator`, and the 14 richer section configurators extend it. The class name no longer implies a "popular sections" section type. — `src/Filament/Configurators/Sections/RichSectionConfigurator.php` + subclasses — effort **S**.
8. **Done/Shipped: Localise demo content instead of hardcoded English.** — `BuildSectionDemoDataAction` now routes core demo summaries, CTA labels, and accordion sample copy through `capell-content-sections::demo` translation keys, with coverage proving a non-English locale can override the generated marketplace/gallery payload. Additional long-form sample rows can move behind the same namespace as they become certification-critical. — `src/Actions/BuildSectionDemoDataAction.php`, `resources/lang/en/demo.php`, `tests/Unit/SectionRegistryTest.php` — effort **M**.

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
- Direct publishing tests now cover the workspace clone Action and the finalized widget-asset repoint through the Layout Builder boundary Action. The remaining package-local coverage gap is a full completion review across the screenshot fixture states.

### 4.4 Cache safety vs declared budget

**Closed 2026-06-06:** `capell.json` now declares `content-sections` cache tags and `Section` invalidation sources for created, updated, deleted, restored, and force-deleted events. Blueprint and linked-page URL dependencies remain broader cache-depth follow-up, but the stale `queueInvalidation`/empty-source mismatch is closed for the package-owned section model.

### 4.5 Performance budget realism

`frontendRenderBudgetMs: 20` is still tight, but the worst duplicate render path is closed: the contributor reuses cached per-widget section payloads across `data()` and `html()` calls. Multiple sections in one widget container can still multiply view-render work, so future performance work should focus on seeded end-to-end timing inside a full Capell app rather than adding noisy package-level wall-clock assertions. The public Blade itself correctly avoids DB queries (relations are pre-hydrated and guarded with `relationLoaded`), which is good.

### 4.6 i18n

Translation files exist (`resources/lang/en/*`), and `BuildSectionDemoDataAction` now uses `capell-content-sections::demo` for the visible demo summaries, CTA labels, and accordion sample copy (§2.8). Several blades emit literal English only via `__()` keys that may be unmapped. Verify every `capell-content-sections::section.{key}.label/description` key is defined, since `DefaultSectionDefinitionProvider` references all 17.

### 4.7 Manifest / docs accuracy (tech debt)

- **Closed 2026-06-15:** `contributes[]` now covers the admin resource, configurators, `section` page type, asset, model, frontend components, the section schema-extender tag, and screenshot fixture routes. The schema-extender row names the extension point rather than a concrete extender class because section configurators consume package-owned tagged extenders.
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

**Screenshot / media gaps.** The package now promotes real Capell runner admin captures for the section index, create form, edit form/assets panel, and dark-mode index. Dedicated fixture routes now exist for the selector modal and anonymous frontend widget gallery; the remaining media work is to recapture and promote those route-backed PNGs once the screenshot runner is producing styled Capell output. A per-section thumbnail strip would be the single highest-leverage media improvement.

**Suggested keywords/tags (8–12):** `page-sections`, `content-blocks`, `reusable-content`, `hero`, `pricing-section`, `faq`, `testimonials`, `cms-sections`, `block-library`, `layout-builder`, `filament`, `foundation`.

## 6. Prioritized Roadmap

| Item                                                                                                         | Bucket | Effort | Impact   | Section ref                                                                                                                                                     |
| ------------------------------------------------------------------------------------------------------------ | ------ | ------ | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Shipped 2026-06-04: sanitise editor HTML at the contributor boundary (kill stored-XSS)                       | Done   | M      | Critical | §2.1 / §4.1                                                                                                                                                     |
| Shipped 2026-06-04: add anonymous public-output sanitisation tests                                           | Done   | M      | Critical | §4.3                                                                                                                                                            |
| Shipped 2026-06-04: implement real `ContentSectionsHealthCheck`                                              | Done   | S      | High     | §2.2 / §4.3                                                                                                                                                     |
| Shipped 2026-06-04: validate `svg()` icon meta against an allow-list                                         | Done   | S      | High     | §4.2                                                                                                                                                            |
| Shipped 2026-06-04: fix manifest accuracy: `contributes`, permissions, tables, surfaces                      | Done   | S      | Med      | §4.7                                                                                                                                                            |
| Shipped 2026-06-07: Benchmark/reduce per-section render cost against the declared budget                     | Done   | S      | High     | §2.3 / §4.5                                                                                                                                                     |
| Declare real `cacheSafety.invalidationSources` / register cache dependency                                   | Done   | S      | Med      | §4.4                                                                                                                                                            |
| Shipped 2026-06-07: Add explicit non-admin public-safety assertions for authenticated frontend visitors      | Done   | S      | High     | §4.3                                                                                                                                                            |
| Closed 2026-06-07: Remove or wire orphaned `simple-list` widget (and the overview doc line)                  | Done   | S      | Med      | §2.6                                                                                                                                                            |
| Shipped 2026-06-07: Extract workspace clone + publish-finalize into Actions behind a layout-builder contract | Done   | M      | Med      | §2.4 / §2.5                                                                                                                                                     |
| Done/Shipped: Add selector-modal and frontend widget-gallery fixture routes; promote remaining shots         | Done   | M      | High     | §5 — dedicated `/screenshot-fixtures/content-sections/*` routes now replace discarded Dashboard/generic fallbacks; promotion waits for styled runner recapture. |
| Shipped 2026-06-07: Fix/relocate stale `docs/mutations.md` + overview editor-workflow bleed                  | Done   | S      | Med      | §4.7                                                                                                                                                            |
| Add shared section-appearance contract (background/spacing/alignment/heading level)                          | Later  | L      | High     | §3                                                                                                                                                              |
| Add per-section anchor IDs + accessible defaults (ARIA, configurable headings)                               | Later  | M      | Med      | §3                                                                                                                                                              |
| Done/Shipped: Localise `BuildSectionDemoDataAction` demo content                                             | Done   | M      | Low      | §2.8 / §4.6                                                                                                                                                     |
| Shipped 2026-06-06: Rename `PopularSectionConfigurator` → `RichSectionConfigurator`                          | Done   | S      | Low      | §2.7                                                                                                                                                            |
