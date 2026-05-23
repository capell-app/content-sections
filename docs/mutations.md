# Content Sections Mutations

Content Sections routes state changes through mutation Actions under `packages/content-sections/src/Actions/Mutations`.

Each mutation accepts `ContentSectionsStateData` and returns `LayoutMutationResultData`. The result contains normalized state, diagnostics, and change summary entries.

The Livewire component should not manually reorder `containers`, `assets`, `originalAssets`, or `selectedRecords` when a mutation Action exists. Add new behavior by creating a mutation Action, testing it directly, then wiring the Livewire method to that Action.

Every mutation must call `NormalizeContentSectionsStateAction` before returning.

## Next

- [Overview](overview.md)
