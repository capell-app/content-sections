# Changelog

All notable changes to `capell-app/content-sections` will be documented in this file.

## Unreleased

- Added dedicated route-backed screenshot fixture states for the section selector modal and anonymous section widget gallery.
- Added provider-backed health diagnostics for the sections table, morph alias, admin resource, default section registry, and public payload contributor.
- Normalised editor-selected section icon keys before public rendering and added manifest coverage for the package's contributions, permissions, capabilities, supported integrations, required table, and section invalidation source.
- Prepared package metadata and documentation for ongoing Capell 4.x package work.

## 2026-06-03

- Security: sanitise editor-authored section summaries and nested meta HTML at the public widget payload boundary, neutralising stored-XSS in anonymous frontend output while preserving legitimate rich-text markup (`SanitizeSectionHtmlAction`, `SectionPublicWidgetPayloadContributor`).
- Added anonymous public-output safety tests proving `<script>` and inline event handlers are stripped from section summaries and meta, and that safe rich text survives.
- Marketplace: rewrote the `capell.json` summary and top-level description, and aligned the `composer.json` description, to describe the 17-section catalog and its benefits.
