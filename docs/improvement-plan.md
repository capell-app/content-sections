# Content Sections — Improvement & Growth Plan
> Package: capell-app/content-sections · Kind: package · Tier: free · Product group: Capell Foundation · Bundle: foundation · Status: Draft

## 1. Snapshot

Content Sections ships a single Eloquent model (`Section`, nested-set, soft-deletes, translatable, media, userstamps) and a catalog of reusable, blueprint-backed page-section widgets that editors manage in a Filament `SectionResource` and that render on the public frontend via package-owned Blade components. It is a runtime + admin package (`surfaces: ["admin","frontend"]`) that bridges into `capell-app/block-library` (each registered section is exposed as a `section.{key}` block) and `capell-app/layout-builder` (public render is driven by `SectionPublicWidgetPayloadContributor`, which the layout graph calls per placed widget). Hard deps: `admin`, `block-library`, `core`, `frontend`, `layout-builder`; optional integration with `publishing-studio` (workspace clone/finalize) and `public-actions`.

**Section types actually shipped.** `DefaultSectionDefinitionProvider` registers **17** definitions: `content`, `hero`, `testimonial`, `accordion`, `call_to_action`, `comparison`, `counter`, `divider`, `faq`, `features`, `logos`, `pricing`, `stats`, `table`, `tabs`, `team`, `timeline` (`src/Support/DefaultSectionDefinitionProvider.php`). Each maps to a configurator in `SectionConfiguratorEnum` and a Blade view under `resources/views/components/section/widgets/`. Note: `PopularSectionConfigurator` (507 lines, `src/Filament/Configurators/Sections/PopularSectionConfigurator.php`) is **not** a "popular sections" type — it is the shared base class that 14 configurators extend; the name is misleading. Two frontend components (`section.widget`, `section.team-member`) are registered with the frontend component registry; the rest render through the dynamic-component fallback.

**Marketplace summary (verbatim):** "Content Sections provides reusable content sections for Capell admin and frontend surfaces." This is identical to the manifest `description` and is pure boilerplate. **Screenshots:** the marketplace block declares **1** image (`docs/assets/marketplace/extension-card.jpg`, a generic card). `docs/screenshots.json` defines a richer **5-entry** capture contract (index, create, edit-with-assets, selector modal, frontend widget gallery), but `docs/screenshots/` does not exist and none are wired into `marketplace.screenshots` — a clear mismatch between the intended and advertised media.

## 2. Improvements (existing functionality)

1. **Escape public section output (single render helper) — see §4.1.** — Public Blade renders editor content with `{!! !!}` across every widget; the cleanest fix point is a sanitisation pass on the data the contributor builds rather than 20 templates. — `src/Support/SectionPublicWidgetPayloadContributor.php` (`sectionData`/`renderSection`) — effort **M**.
2. **Replace the stub health check with real surface/install assertions.** — `ContentSectionsHealthCheck` only returns `'^4.0'`; it never checks anything, yet the manifest labels it "package surfaces, providers, and install health are discoverable by Diagnostics" at `severity: critical`. Implement checks that the `Section` morph alias is registered, the `SectionResource` is contributed, the `sections` table exists, and the default-section registry is populated. — `src/Health/ContentSectionsHealthCheck.php`, `capell.json` healthChecks — effort **S**.
3. **Avoid per-section view recompilation in the render loop.** — `renderSection` falls back to `Blade::render(..., deleteCachedView: true)`, which compiles and then deletes the compiled view on every call; with multiple sections per page this recompiles repeatedly and will breach `frontendRenderBudgetMs: 20`. Prefer the named-view path (already present) and drop `deleteCachedView`, or pre-resolve a cached compiled component. — `src/Support/SectionPublicWidgetPayloadContributor.php:128-157` — effort **S**.
4. **Decouple the cross-package `widget_assets` write in publish finalize.** — `finalizeSectionPublish` issues a raw `DB::table('widget_assets')->update(...)` against a layout-builder-owned table, bypassing models/observers and hard-coding another package's schema. Move this behind a layout-builder contract/Action (e.g. a "repoint widget asset to live section" service) so the boundary stays at public contracts (the arch test in `tests/Arch/ContentSectionsBoundaryTest.php` already polices direct `Capell\LayoutBuilder` references). — `src/Providers/ContentSectionsServiceProvider.php:450-476` — effort **M**.
5. **Move provider wiring into Actions per package convention.** — `cloneSectionIntoWorkspace` and `finalizeSectionPublish` are ~70 lines of domain logic living in the service provider; the package's own `docs/mutations.md` and Maintenance Notes say behaviour belongs in `src/Actions`. Extract to `CloneSectionIntoWorkspaceAction` / `FinalizeSectionPublishAction` and test directly. — `src/Providers/ContentSectionsServiceProvider.php:406-476` — effort **M**.
6. **Delete or wire the orphaned `simple-list` widget.** — `resources/views/components/section/widgets/simple-list.blade.php` has no entry in `DefaultSectionDefinitionProvider`, `SectionConfiguratorEnum`, or frontend registration, so it is unreachable dead code — yet `docs/overview.md` lists "simple list" as a shipped public widget family. Either register it (configurator + definition + lang keys) or remove the view and the doc line. — `resources/views/components/section/widgets/simple-list.blade.php`, `src/Support/DefaultSectionDefinitionProvider.php`, `docs/overview.md` — effort **S**.
7. **Rename `PopularSectionConfigurator` to its actual role.** — It is the shared base configurator, not a section type; the name misleads readers (and reviewers grepping for a "popular" section). Rename to e.g. `BaseSectionConfigurator`/`RichSectionConfigurator` and update the 14 subclasses. — `src/Filament/Configurators/Sections/PopularSectionConfigurator.php` + subclasses — effort **S**.
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
- `{!! $summary !!}` appears in `content`, `hero`, `faq`, `call-to-action`, `pricing`, `logos`, `counter`, `stats`, `team`, `tabs`, `accordion`, `timeline`, `features`, and the orphaned `simple-list` views (`resources/views/components/section/widgets/*.blade.php`).
- Nested meta is also raw: `{!! $question['answer'] !!}` (`faq.blade.php:35`), `{!! $item['content'] !!}` (`accordion.blade.php:44`), `{!! $tab['content'] !!}` (`tabs.blade.php:48`), and `simple-list` lines 54/60.
- The source data is unsanitised: `SectionPublicWidgetPayloadContributor::renderSection` wraps summary in `new HtmlString((string) $data['summary'])` (line 139), and `meta` flows straight from `Section->meta` JSON via `metaFor()` with only `array_replace_recursive`. There is no HTML Purifier / allow-list anywhere in the package.
- Risk: any actor who can edit a section's translation `content`/`summary` or `meta` (a non-super editor with `update` permission, or any upstream package that writes section meta) can persist `<script>`/event-handler HTML that executes for every anonymous visitor. This directly violates the public-output-safety rule. Fix at the contributor boundary (§2.1) by passing section content through a shared sanitiser before it reaches `HtmlString`/the views.

### 4.2 Unvalidated icon strings into `svg()` (render safety)
`features.blade.php:29` and `counter.blade.php:30` do `{!! svg($feature['icon'], ...)->toHtml() !!}` where `$feature['icon']` / `$counter['icon']` is an editor-supplied meta string. Confirm `svg()` cannot resolve arbitrary filesystem paths or emit attacker-controlled markup; at minimum validate against a known icon allow-list.

### 4.3 Test gaps (no proof of public safety)
- `tests/` has **no** XSS / escaping / sanitisation test — confirmed by grep (`purif|sanitiz|escape|XSS|script>` → 0 hits in `tests/`). `tests/Feature/SectionRenderingTest.php` (19 tests) and the boundary test exercise rendering and dependency boundaries, but nothing asserts that a `<script>` in summary/meta is neutralised — because it currently is not.
- The health check is only asserted to *exist* (`tests/Unit/ContentSectionsCoverageTest.php`), with no behavioural coverage — unsurprising, since it has no behaviour.
- No test covers the `finalizeSectionPublish` `widget_assets` repoint or the workspace clone path beyond the publishing feature test. The capell skill requires rendering/cache changes to ship with anonymous + non-admin safety tests; this package does not yet meet that bar.

### 4.4 Cache safety vs declared budget
`capell.json` declares `cacheSafety.cacheable: false`, `queueInvalidation: true`, but `invalidationSources: []` — nothing is declared to drive the queued invalidation, and the package registers no `CacheInvalidationRegistry::registerDependency()`. Either declare the real invalidation sources (section save/delete, blueprint change, linked-page URL change) or correct the flags. The `SectionObserver` exists (`src/Observers/SectionObserver.php`) but its invalidation contract is not reflected in the manifest.

### 4.5 Performance budget realism
`frontendRenderBudgetMs: 20` is optimistic given (a) per-section `Blade::render(deleteCachedView: true)` recompiles (§2.3) and (b) `Section::getMorphRelations()` eager-loads `ancestors.blueprint`, `media`, `image`, `linkedPage.translation/pageUrl.siteDomain`, `translation`, `blueprint` per section. Multiple sections in one widget container can multiply this. Benchmark against the budget; the public Blade itself correctly avoids DB queries (relations are pre-hydrated and guarded with `relationLoaded`), which is good.

### 4.6 i18n
Translation files exist (`resources/lang/en/*`), but `BuildSectionDemoDataAction` bypasses them with hardcoded English (§2.8), and several blades emit literal English only via `__()` keys that may be unmapped. Verify every `capell-content-sections::section.{key}.label/description` key is defined, since `DefaultSectionDefinitionProvider` references all 17.

### 4.7 Manifest / docs accuracy (tech debt)
- `contributes: []` while the provider contributes an admin resource, ~18 configurators, a `section` page type, an asset, two frontend components, and the public widget contributor; `contributionTraceability.deferredContributions` lists `admin-resource, asset, configurator, model, page-type, route, schema-extender` — none declared in `contributes[]`.
- `permissions: []` while `SectionPolicy` relies on Shield permissions (`view_any`, `view`, `create`, `update`, `delete`, `restore`, `force_delete`, `replicate`, `reorder`).
- `surfaces` mismatch: `capell.json` = `["admin","frontend"]`; `README.md` = "Filament admin, Livewire, database" (drops frontend, adds Livewire/database).
- `database.requiredTables: []` though the package owns and requires the `sections` table.
- **`docs/mutations.md` describes a subsystem that does not exist in this package** — `src/Actions/Mutations`, `ContentSectionsStateData`, `NormalizeContentSectionsStateAction`, `LayoutMutationResultData` are absent here (they belong to layout-builder). The doc, and the `content_first`/`layout_first`/breakpoint/undo-redo narrative in `docs/overview.md`, are bleed from another package and mislead maintainers.

## 5. Marketplace & Positioning

**Role.** Free, foundation-bundled, first-party — correct. This package is platform glue: it is what makes a Capell site able to assemble pages from reusable, themeable content blocks, and it is the producer side of the block-library/layout-builder pairing. That makes it a keystone of the foundation pitch ("compose pages from reusable sections, no markup in content fields"), so its marketing should sell the *catalog* and *reuse*, not describe itself tautologically.

**Current `summary` / `description` critique.** Both read "Content Sections provides reusable content sections for Capell admin and frontend surfaces." — circular ("content sections provides content sections"), lists no section types, and conveys no benefit. The composer `description` ("Reusable content sections for Capell") is the same problem, shorter.

**Improved summary (marketplace):** "Seventeen ready-to-use, themeable page sections — hero, FAQ, pricing, stats, testimonials, team, comparison, timeline and more — that editors reuse across pages and developers render through safe, package-owned Blade. Bundled free with Capell Foundation."

**Improved composer `description`:** "A free catalog of 17 reusable, blueprint-backed page sections (hero, FAQ, pricing, stats, team, comparison, timeline, and more) for Capell, rendered through layout-builder and block-library."

**Free/bundle vs premium upsell.** Keep the catalog free — it is foundation table-stakes and drives adoption of the paid layout/publishing tooling. The natural premium upsell line is *advanced* section types and capabilities (A/B-testable sections, personalised/segmented sections, form/CRM sections, animation presets), which the demo copy already hints at ("Advanced widgets stay in optional packages", "Pro bundle: 0"). Position this package as the on-ramp and reserve those as paid add-on packages.

**Screenshot / media gaps.** The single generic `extension-card.jpg` undersells a visual, 17-type catalog. `docs/screenshots.json` already specifies the right 5 captures (index, create, edit+assets, selector modal, frontend widget gallery); capture them in a seeded demo (with `block-library` + `layout-builder`) and surface at least the widget gallery + admin index in `marketplace.screenshots`. A per-section thumbnail strip would be the single highest-leverage media improvement.

**Suggested keywords/tags (8–12):** `page-sections`, `content-blocks`, `reusable-content`, `hero`, `pricing-section`, `faq`, `testimonials`, `cms-sections`, `block-library`, `layout-builder`, `filament`, `foundation`.

## 6. Prioritized Roadmap

| Item | Bucket | Effort | Impact | Section ref |
| --- | --- | --- | --- | --- |
| Sanitise editor HTML at the contributor boundary (kill stored-XSS) | Now | M | Critical | §2.1 / §4.1 |
| Add anonymous + non-admin public-safety tests (script in summary/meta is neutralised) | Now | M | Critical | §4.3 |
| Implement real `ContentSectionsHealthCheck` (table, morph, resource, registry) | Now | S | High | §2.2 / §4.3 |
| Validate `svg()` icon meta against an allow-list | Now | S | High | §4.2 |
| Fix manifest accuracy: `contributes`, `permissions`, `requiredTables`, `surfaces` | Now | S | Med | §4.7 |
| Drop `deleteCachedView` per-section recompile; prefer named view | Next | S | High | §2.3 / §4.5 |
| Declare real `cacheSafety.invalidationSources` / register cache dependency | Next | S | Med | §4.4 |
| Remove or wire orphaned `simple-list` widget (and the overview doc line) | Next | S | Med | §2.6 |
| Extract workspace clone + publish-finalize into Actions behind a layout-builder contract | Next | M | Med | §2.4 / §2.5 |
| Rewrite marketplace `summary` + composer `description`; capture the 5 screenshots | Next | M | High | §5 |
| Fix/relocate stale `docs/mutations.md` + overview editor-workflow bleed | Next | S | Med | §4.7 |
| Add shared section-appearance contract (background/spacing/alignment/heading level) | Later | L | High | §3 |
| Add per-section anchor IDs + accessible defaults (ARIA, configurable headings) | Later | M | Med | §3 |
| Localise `BuildSectionDemoDataAction` demo content | Later | M | Low | §2.8 / §4.6 |
| Rename `PopularSectionConfigurator` → base/rich configurator | Later | S | Low | §2.7 |
