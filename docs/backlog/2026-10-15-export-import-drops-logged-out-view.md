# Layout export and import leave out the logged-out view

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the logged-out view plan (`docs/plans/2026-10-15-logged-out-view.md`, Requirements › Out of scope), 2026-10-15, branch `trace/feat/logged-out-view`. Left out to keep that change small.

## Today

- A layout's logged-out view is the `Layout::META_LOGGED_OUT` meta, read into `Layout::$logged_out_content` by `Layout::from_post()`. `LayoutRepository::duplicate()` copies it through `insert_draft()`.
- Pro's export, `LayoutExportRestController`, writes each layout's `title`, `content`, `rules` and `design`, but not the logged-out view.
- Import does the same. `LayoutImportReview::layout()` projects `title`, `content`, `rules` and `design`, and `LayoutRepository::import()` passes no logged-out view to `insert_draft()`.
- The import types copy that shape without it: `ImportReview` in `packages/blocks/src/checkout/import-export.tsx`, and `layoutExportWireSchema` in `apps/admin/src/lib/schema/tools.schema.ts`.
- The editor's Import / Export panel is hidden in the logged-out view (`isLoggedOutView()` in `import-export.tsx`), so an import can't land in the meta.

## Why it matters

A merchant who moves a layout to another store by export and import loses its logged-out view without being told. On a store that requires login, guests on the imported layout get WooCommerce's bare "You must be logged in" message instead of the merchant's design.

## Suggested fix

Add a `logged_out_content` string to each exported layout. Project it in `LayoutImportReview::layout()`, defaulting to `''` when the file has none. Pass it from `LayoutRepository::import()` to `insert_draft()`. Add it to both TypeScript copies. An editor import, which replaces only the open view's blocks, can keep ignoring it.

## Done when

- [ ] `LayoutExportRestController`'s layout entries include a `logged_out_content` key holding `Layout::$logged_out_content`.
- [ ] `LayoutImportReview::layout()` returns `logged_out_content`, as `''` when the file has none.
- [ ] `LayoutRepository::import()` passes the file's logged-out view to `insert_draft()`, so an imported layout's `_yaycheckout_logged_out_content` meta matches the file's.
- [ ] `layoutExportWireSchema` in `apps/admin/src/lib/schema/tools.schema.ts` accepts `logged_out_content`.
