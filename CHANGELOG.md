# Changelog

All notable changes to `capell-app/content-sections` will be documented in this file.

## Unreleased

- Added a section media workflow for logo and team items: images upload to the section's block media collection (always on a public disk, even when Filament's default disk is private), each item stores a Spatie media uuid validated against that collection (public-disk images only), and the public payload contributor and shared fragments resolve it to a plain public URL and alt text (never the uuid) through `ResolveSectionMediaAction`. Alt text and the decorative flag come from Core's media metadata, in the page language with the default language as fallback. Missing, deleted, non-image or non-public media (including a disk with no visibility configured) falls back to text, and an editor can clear a reference to deleted media. Workspace drafts and duplicated sections copy all of the section's media (image, video and block media) with files and alt text in one transaction, through `CopySectionMediaAction`, which replaces the earlier row-only copy of the image collection that left clones pointing at missing files; each copy gets a new uuid, meta references are remapped, an unreadable or empty source file clears its reference, and an aborted copy leaves no files behind. Cached shared section fragments refresh when media attached to the section is replaced, deleted, restored, or has its alt text or decorative flag edited, without the section being saved. Per-item uploads made with the earlier image field are not migrated and render as text until an image is chosen.
- Blueprint selectors use the registered subject so unrelated blueprint types are not offered or selected by default.

- Added translated demo copy defaults for section gallery payloads and coverage for locale overrides.
- Added dedicated route-backed screenshot fixture states for the section selector modal and anonymous section widget gallery.
- Added provider-backed health diagnostics for the sections table, morph alias, admin resource, default section registry, and public payload contributor.
- Normalised editor-selected section icon keys before public rendering and added manifest coverage for the package's contributions, permissions, capabilities, supported integrations, required table, and section invalidation source.
- Prepared package metadata and documentation for ongoing Capell 0.0.x package work.

## 2026-06-03

- Security: sanitise editor-authored section summaries and nested meta HTML at the public layout widget payload boundary, neutralising stored-XSS in anonymous frontend output while preserving legitimate rich-text markup (`SanitizeSectionHtmlAction`, `SectionPublicLayoutWidgetPayloadContributor`).
- Added anonymous public-output safety tests proving `<script>` and inline event handlers are stripped from section summaries and meta, and that safe rich text survives.
- Marketplace: rewrote the `capell.json` summary and top-level description, and aligned the `composer.json` description, to describe the 17-section catalog and its benefits.
