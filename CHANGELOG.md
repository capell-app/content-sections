# Changelog

All notable changes to `capell-app/content-sections` will be documented in this file.

## Unreleased

- Prepared package metadata and documentation for ongoing Capell 4.x package work.

## 2026-06-03

- Security: sanitise editor-authored section summaries and nested meta HTML at the public widget payload boundary, neutralising stored-XSS in anonymous frontend output while preserving legitimate rich-text markup (`SanitizeSectionHtmlAction`, `SectionPublicWidgetPayloadContributor`).
- Added anonymous public-output safety tests proving `<script>` and inline event handlers are stripped from section summaries and meta, and that safe rich text survives.
- Marketplace: rewrote the `capell.json` summary and top-level description, and aligned the `composer.json` description, to describe the 17-section catalog and its benefits.
