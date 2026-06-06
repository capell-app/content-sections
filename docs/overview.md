---
title: 'Content Sections Overview'
description: 'How the Capell Content Sections package adds reusable section records, admin editing, and frontend section rendering.'
---

# Content Sections Overview

Content Sections adds reusable page sections that can be edited in the Capell admin and rendered through Block Library Blade components on the public frontend.

Use it when a site needs shared heroes, FAQs, pricing widgets, statistics, testimonials, timelines, tables, teams, logos, and similar structured page sections without storing presentation markup in page content fields.

## Hard Dependencies

- `capell-app/admin`
- `capell-app/block-library`
- `capell-app/core`
- `capell-app/frontend`
- `capell-app/layout-builder`

## What It Adds

- `SectionResource` in the admin content navigation under Pages.
- Create, edit, and list pages for reusable section records.
- A section assets relation manager for records with attached assets.
- Section blueprint/configurator support for common marketing and editorial widgets.
- Frontend payload wiring for rendered Block Library section widgets.
- Livewire helpers used by admin asset and widget selection workflows.
- Public-output sanitisation for editor-authored rich text, nested meta HTML, and icon keys before section data reaches anonymous frontend Blade.
- Real package diagnostics for storage, morph registration, admin resource availability, registry population, and the Layout Builder public payload contributor.

## Admin Surfaces

| Surface                        | Purpose                                                                             |
| ------------------------------ | ----------------------------------------------------------------------------------- |
| `SectionResource` index        | Browse and filter reusable sections.                                                |
| `CreateSection`                | Create a section from a registered blueprint.                                       |
| `EditSection`                  | Edit section details, translations, related content, settings, actions, and assets. |
| `SectionAssetsRelationManager` | Manage assets attached to a section.                                                |
| `SectionAlertsWidget`          | Shows section-level warnings when editing records.                                  |
| `ModalTableSelect`             | Admin selection modal used when linking section content.                            |

## Editor Workflow

Editors create a reusable section record from a registered blueprint, fill the section translation and structured meta fields, then attach assets when the section type supports them.

Layout Builder and Block Library own placement, layout modes, breakpoints, and undo/redo behavior. Content Sections owns the reusable records, section configurators, safe public render payloads, and publishing hooks that keep attached widget assets pointed at the current published section.

When a section is used in a layout, the public payload contributor receives preloaded widget assets and renders the matching section view without querying from Blade or exposing editor state.

## Frontend Surfaces

Content Sections renders section records through Block Library views under `capell-block-library::blocks.catalog.*`.

Section summaries and nested meta are sanitised at the payload-contributor boundary, so Block Library views can preserve safe rich text without exposing scripts, event handlers, or untrusted icon identifiers.

The package-owned public widget views include:

- accordion
- call to action
- comparison
- content
- counter
- divider
- FAQ
- features
- hero
- logos
- pricing
- stats
- table
- tabs
- team
- testimonial
- timeline

Public views should receive hydrated render data from Capell payload builders and components. They should not query the database or expose admin/editor state.

## Screenshot Coverage

The screenshot contract is stored in [screenshots.json](screenshots.json). Marketplace media now promotes Capell runner captures for:

- admin section index;
- create section form;
- edit section form with publishing controls and the asset relation manager shell.

The remaining screenshot fixture states are:

- modal section/widget selector;
- a frontend page rendering each registered section widget family.

Discard Dashboard or generic demo-page fallbacks for those remaining states; they need dedicated routes or browser actions before promotion.

## Install And Verify

Install in a Capell app with only the hard dependencies listed above:

```bash
composer require capell-app/content-sections
```

Then run the package tests from this repository:

```bash
vendor/bin/pest packages/content-sections/tests --configuration=phpunit.xml
```

## Developer Docs

Package behavior is documented in this overview and the repository improvement plan. Layout editor mutations are owned by `capell-app/layout-builder`.

## Known Audit Notes

Content Sections has both admin and frontend surfaces. Final visual screenshots should be captured from a seeded app that includes `layout-builder` and `block-library`, because those are hard dependencies for editing and rendering section widgets.
