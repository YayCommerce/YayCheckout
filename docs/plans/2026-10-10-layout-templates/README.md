# Layout Templates Implementation Plan

> **For agentic workers:** execute phases in order. Read this file, then the current phase file. Do not start a phase until the previous phase's exit criterion has been verified and `/yayplancheck` has passed on it.

**Status:** active
**Goal:** Layouts are created from per-template files (markup plus design meta), unbuilt templates show as Coming soon, Reset restores a layout's own template, the setup wizard loses its Brand step, and template 01 (Classic two-column) replaces the default layout and matches its mock.
**Spec:** [docs/specs/2026-10-10-layout-templates-design.md](../../specs/2026-10-10-layout-templates-design.md)
**Architecture:** `LayoutTemplateCatalogue` stays the single owner of the template list and the plan lock, and gains the file lookups (`templates/layouts/<slug>/markup.php` and `design.json`). `LayoutRepository` takes a slug on every insert and stores it in `_yaycheckout_template`, so Reset can find the template again. Template data stays beside the markup (spec approach B), not in the PHP array.
**Touches:**
- PHP:
  - `includes/Controllers/LayoutTemplateCatalogue.php`
  - `includes/Models/Layout.php`
  - `includes/Repositories/LayoutRepository.php`
  - `includes/Controllers/LayoutRestController.php`
  - `includes/Controllers/SetupRestController.php`
- Templates: `templates/default-layout.php` (moved to `templates/layouts/classic-two-column/markup.php`) and the new `templates/layouts/classic-two-column/design.json`
- SPA:
  - `apps/admin/src/lib/schema/template.schema.ts` (shared: read by `TemplatePicker`, `StartFromStep`, `FinishStep`, `LibraryStep`, `NewLayoutModal`)
  - `apps/admin/src/components/shared/template-picker/TemplatePicker.tsx` (shared: the New layout modal and the wizard)
  - `apps/admin/src/components/setup-wizard/*`
  - `apps/admin/src/lib/{api,schema,queries}/setup.*`
  - `apps/admin/src/pages/tools/components/Reset{Card,Modal}.tsx`
- Docs and mirror comments: `docs/architecture.md`, `docs/conventions.md`, `.claude/rules/blocks.md`, `packages/blocks/src/shared/address-template.ts`, `packages/blocks/src/shared/address-fields.ts`, `packages/blocks/src/order-summary/edit.tsx`

**Tests:** `tests/Integration/LayoutTemplateCatalogueTest.php` (suite `Integration`). It checks that every available template's markup passes `LayoutMarkupReader::publish_error()`, and that its `design.json` sets every section and survives `LayoutDesign::from_meta()` unchanged. It guards against shipping a template that creates an unpublishable or half-styled layout. The user accepted it in the spec.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in the guarded `boot_pro()` in `includes/YayCheckout.php`; `composer lint` runs `scripts/check-pro-boundary.sh` and must pass.
- Pro features are withheld by PHP; the SPA's `isPro` only decides what the SPA shows.
- Every meta key is a class constant on the class that owns it: `Layout::META_TEMPLATE = '_yaycheckout_template'`. Like `Layout::META_RULES`, it is not registered with `register_post_meta()`.
- Everything on the wire is snake_case; the SPA converts only in `apps/admin/src/lib/api/<resource>.api.ts` and parses with strict wire schemas.
- `markup.php` is the markup exactly as the block editor saves it. PHP appears only for translated strings (`yaycheckout` text domain), the logo-or-title choice and conditional links.
- Every insert goes through `LayoutRepository::insert_draft()` (Free cap `LayoutRepository::FREE_LIMIT = 3`, `wp_slash()`).
- Never hand-edit `vendor/`, `vendor-prefixed/`, `node_modules/`, `assets/dist/`, `release/`; rebuild with `pnpm build:blocks` / `./scripts/run.sh build`.
- Git:
  - All work goes on one branch, `trace/feat/layout-templates`, created from `trace/docs/layout-templates` (which holds the spec commit), and lands as one squash PR into `dev/edd`.
  - Commit messages are Conventional Commits (`type(scope): summary`) ending with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`.
  - Stage files by name.
  - Commit or push only when the user asks.
- Known failing checks on `dev/edd`: `pnpm --filter @yaycheckout/admin-page lint` exits 1, with 4 eslint warnings and prettier failures in `apps/admin/src/lib/utils.ts`, `apps/admin/src/styles/custom-header.css` and `apps/admin/tsconfig.node.json`. Expect no new failures beyond those.

## Review focus

- **A slug from a request reaches a file path.** A crafted `template_slug` such as `../../x` must never be included. `markup()` and `design()` resolve only slugs present in `catalogue()`. Owned by Phase 1 Task 1; verified in its step 6.
- **A template whose lock and availability both fail.** Expected: the Pro message wins, because the lock check runs first. Phase 1 Task 1, step 6.
- **Reset on a layout with no slug, or one whose template has since become Coming soon.** Expected: Classic two-column's markup and design, and no error. Phase 1 Task 2, step 5.
- **Reset on a layout made from a Pro template, on a Free site.** Expected: it restores that template (no lock check on Reset). Today no Pro template is available, so this is checked by reading the code in Phase 1 Task 2, step 5.
- **Reset wipes design meta, which has no revisions.** The confirm modal must say "Your design changes for this layout will be lost." Phase 1 Task 3, step 4.
- **The wizard's `409` path and `useCompleteSetupMutation` still read `design`.** After Phase 2, nothing in the SPA may parse or cache a `design` from `/setup`. Phase 2 Task 4.
- **Template 01's markup invalid in the editor.** Expected: a fresh layout opens with no "This block contains unexpected or invalid content" warning. Phase 3 Task 5.

## Phases

| Phase | Delivers (user-visible) | Exit criterion (exact check) | Status |
|---|---|---|---|
| 1 | New, Blank, library and Reset layouts come from template files with their own design; unbuilt templates show Coming soon | `composer test -- --filter LayoutTemplateCatalogueTest` passes. In the SPA, a "From library" Classic two-column layout opens in the editor with a `#f0f0f1` canvas background, and every other template shows Coming soon | pending |
| 2 | Setup wizard is Scan → Start from → Finish, with no Brand step, and setup no longer writes the global design | Run the wizard from Settings (re-run): no Brand step; `wp option get yaycheckout_design` is unchanged before and after; the created layout's `_yaycheckout_template` is `classic-two-column` | pending |
| 3 | Template 01 matches its mock as far as existing blocks allow | The spec's acceptance criterion 12 end-to-end walkthrough passes | pending |

## Interface contract

- `LayoutTemplateCatalogue::DEFAULT_SLUG = 'classic-two-column'` (public const). Produced by Phase 1 Task 1; consumed by Phase 1 Task 2 and Phase 2 Task 4.
- `LayoutTemplateCatalogue::templates( bool $is_pro ): array`, returning `array{ slug: string, name: string, category: string, locked: bool, available: bool }[]`. Produced by Phase 1 Task 1; consumed by `GET /yaycheckout/v1/layouts/templates` and Phase 1 Task 3 (wire field `available`).
- `LayoutTemplateCatalogue::is_available( string $slug ): bool`: true only when `$slug` is in `catalogue()` and both `templates/layouts/<slug>/markup.php` and `design.json` exist. Produced by Phase 1 Task 1; consumed by Phase 1 Task 2 (`reset()`).
- `LayoutTemplateCatalogue::assert_available( string $slug ): void`: throws `\InvalidArgumentException`.
  - `'Unknown template.'` when the slug isn't in the catalogue;
  - otherwise `'This template is part of YayCheckout Pro.'` when it is locked;
  - otherwise `'This template is coming soon.'` when it is unavailable.

  Produced by Phase 1 Task 1.
- `LayoutTemplateCatalogue::markup( string $slug ): string` and `LayoutTemplateCatalogue::design( string $slug ): LayoutDesign`: both throw `\InvalidArgumentException` when `! is_available( $slug )`. Produced by Phase 1 Task 1; consumed by Phase 1 Task 2.
- `Layout::META_TEMPLATE = '_yaycheckout_template'` and `Layout::$template` (string, `''` when unset; not in `to_array()`). Produced by Phase 1 Task 2.
- `LayoutRepository::create( string $title, string $template_slug ): Layout`. It throws `\InvalidArgumentException` from the catalogue or from the cap. Produced by Phase 1 Task 2; consumed by `LayoutRestController::create_draft()` and by `SetupRestController::complete_setup()` (Phase 1 Task 2 updates the call; Phase 2 Task 4 rewrites the method).
- `LayoutRepository::reset( int $id ): Layout`: rewrites `post_content` and `Layout::META_DESIGN` from the stored slug, or from `DEFAULT_SLUG`. Produced by Phase 1 Task 2.
- `LayoutRepository::default_markup()`: **removed** in Phase 1 Task 2.
- Wire `GET /layouts/templates` entry: `{ slug, name, category, locked, available }`; SPA `Template.available: boolean`. Produced by Phase 1 Tasks 1 and 3.
- `POST /yaycheckout/v1/setup` args: `{ template_slug: string, rerun?: boolean }`; response `{ layout }`. SPA `CompleteSetupPayload = { templateSlug: string; rerun?: boolean }` and `SetupResult = { layout: Layout }`. Produced by Phase 2 Task 4.
- `templates/layouts/classic-two-column/markup.php`: moved unchanged in Phase 1 Task 1; rebuilt in Phase 3 Task 5.
