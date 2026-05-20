---
title: 'Content Sections Overview'
description: 'How the Capell Content Sections package adds reusable section records, admin editing, and frontend section rendering.'
---

# Content Sections Overview

Content Sections adds reusable page sections that can be edited in the Capell admin and rendered through package-owned Blade components on the public frontend.

Use it when a site needs shared heroes, FAQs, pricing blocks, statistics, testimonials, timelines, tables, teams, logos, and similar structured page sections without storing presentation markup in page content fields.

## Hard Dependencies

- `capell-app/admin`
- `capell-app/content-blocks`
- `capell-app/core`
- `capell-app/frontend`
- `capell-app/layout-builder`

## What It Adds

- `SectionResource` in the admin content navigation under Pages.
- Create, edit, and list pages for reusable section records.
- A section assets relation manager for records with attached assets.
- Section blueprint/configurator support for common marketing and editorial blocks.
- Frontend Blade components for rendered section blocks.
- Livewire helpers used by admin asset and block selection workflows.

## Admin Surfaces

| Surface                        | Purpose                                                                             |
| ------------------------------ | ----------------------------------------------------------------------------------- |
| `SectionResource` index        | Browse and filter reusable sections.                                                |
| `CreateSection`                | Create a section from a registered blueprint.                                       |
| `EditSection`                  | Edit section details, translations, related content, settings, actions, and assets. |
| `SectionAssetsRelationManager` | Manage assets attached to a section.                                                |
| `SectionAlertsWidget`          | Shows section-level warnings when editing records.                                  |
| `ModalTableSelect`             | Admin selection modal used when linking section content.                            |

## Frontend Surfaces

Content Sections renders through package Blade views under `resources/views/components/section`.

The package-owned public block views include:

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
- simple list
- stats
- table
- tabs
- team
- testimonial
- timeline

Public views should receive hydrated render data from Capell payload builders and components. They should not query the database or expose admin/editor state.

## Screenshot Coverage

The screenshot contract is stored in [screenshots.json](screenshots.json). The first isolated audit pass found these surfaces that need final capture in an installed demo app:

- admin section index;
- create section form;
- edit section form with warnings and asset relation manager;
- modal section/block selector;
- a frontend page rendering each registered section block family.

## Install And Verify

Install in a Capell app with only the hard dependencies listed above:

```bash
composer require capell-app/content-sections
```

Then run the package tests from this repository:

```bash
vendor/bin/pest packages/content-sections/tests --configuration=phpunit.xml
```

## Known Audit Notes

Content Sections has both admin and frontend surfaces. Final visual screenshots should be captured from a seeded app that includes `layout-builder` and `content-blocks`, because those are hard dependencies for editing and rendering section blocks.
