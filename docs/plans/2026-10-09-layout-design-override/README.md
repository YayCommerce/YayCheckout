# Per-Layout Design Override Implementation Plan

> **For agentic workers:** execute phases in order. Read this file, then the current phase file. Do not start a phase until the previous phase's exit criterion has been verified and `/yayplancheck` has passed on it.

**Status:** superseded by [docs/plans/2026-10-09-layout-design-override.md](../2026-10-09-layout-design-override.md)
**Goal:** A Pro merchant overrides a layout's design per token group, or adds layout custom CSS, from a Design sidebar in that layout's editor. Every other group keeps following global.
**Spec:** [docs/specs/2026-10-09-layout-design-override-design.md](../../specs/2026-10-09-layout-design-override-design.md)
**Architecture:** A sparse per-group layer in the layout meta `_yaycheckout_design`, saved by core's `wp/v2` with the layout. `DesignRepository::for_layout()` runs the filter `yaycheckout_design_for_layout`, and only Pro hooks it, so Free always prints global. `CheckoutBaseStyles` is the single print point for the storefront and the editor canvas.
**Touches:**
- PHP:
  - `includes/Models/Design.php` (shared, consumed by `DesignRepository`, `DesignStyles`, `CheckoutBaseStyles`, `SetupRestController`, `DesignRestController`)
  - `includes/Models/Layout.php`
  - `includes/Repositories/{DesignRepository,LayoutRepository}.php`
  - `includes/Renderer/{CheckoutBaseStyles,CheckoutPage}.php`
  - `includes/Engine/Admin/LayoutEditorCanvas.php`
  - `includes/Blocks/BlockRegistry.php`
  - `includes/Pro/ProServiceProvider.php`
  - `includes/Pro/Controllers/LayoutExportRestController.php`
  - `includes/Pro/Services/LayoutImportReview.php`
  - `includes/Pro/Controllers/LayoutImportRestController.php`
- Blocks: `packages/blocks/src/checkout/` and `packages/blocks/src/shared/`.
- Docs: `docs/architecture.md`, `docs/decisions/`.

**Tests:** `tests/Integration/Pro/LayoutImportDesignTest.php`, suite `Integration`. It guards that import keeps a file's valid design sections, drops an invalid section without refusing the file, and creates no meta for a file without `design`.

## Deviations from the spec (decided while planning)

- **Base branch.** `dev/edd` already carries the token cleanup the spec waited on: #65 (3c3b9c0) removed the `spacing` and `buttons` sections, design presets and `coerce()`. `Design::schema()` has colours, typography and inputs, the sidebar lists those, and the layer path validates with `is_valid()` alone, as global does.
- **No "Theme default" switch in the sidebar (spec UI, amended).** Switching a group to Custom seeds it from global with `enabled: true`, so Custom always prints the group and Global is the only way to follow the Design page. A stored `enabled: false` from import or `wp/v2` still applies, and editing any value in the sidebar sets it back to true.
- **Unknown keys (spec AC 10, amended).** `validate_layer()` drops an unknown section or key instead of answering 400, matching `Design::sanitize()` for global. A missing key, an invalid value or unsafe CSS still answers 400.
- **`CheckoutColour` is gone on `dev/edd`.** #65 removed it. The primary colour now reaches blocks through `checkout-base.css` (`--yaycheckout-color-primary`), so `CheckoutBaseStyles` covers the spec's `CheckoutColour` item.
- **Editor data.** The spec's "filter on the editor data" becomes a new filter, `yaycheckout_editor_data`, applied in `BlockRegistry`. Pro adds a `design` key carrying the global tokens, `Design::schema()` and `Design::FONT_FAMILIES`. This means the sidebar copies no enums or bounds from PHP. Only the CSS-variable builder mirrors `DesignStyles::vars()` and `INPUT_HEIGHTS`.

## Global constraints

- Product PHP runs on PHP 7.4. None of these: enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types, `mixed`, `static` or `never` types, or a trailing comma after the last parameter.
- Free names Pro only in `boot_pro()`. Pro attaches only through hooks added in `includes/Pro/ProServiceProvider.php`. `composer lint` runs `scripts/check-pro-boundary.sh`.
- Free never applies a layer and never registers the meta. Free's sidebar is a teaser with no real data or endpoint behind it.
- Token rules come only from `Design::schema()`.
- Wire and meta keys are snake_case. The meta value has one shape everywhere: `{ tokens?: { <section>: { <every key of that section> } }, custom_css?: string }`. An empty layer is deleted, never stored as `{}`.
- `Design::CUSTOM_CSS_MAX_BYTES` (50000) applies to the layer's CSS on its own.
- WordPress floor is 6.5. Editor imports come from the same packages `packages/blocks/src/checkout/import-export.tsx` uses. No `__experimentalNavigator*`.
- A TypeScript copy of a PHP rule carries `Mirrors <Owner> in PHP` (docs/conventions.md).
- Never hand-edit `assets/dist/`. Rebuild with `pnpm build:blocks`.
- Use Node 22 (`nvm use`). PHPUnit on this machine: `/opt/homebrew/opt/php@8.4/bin/php vendor/bin/phpunit`.
- Commit messages: `type(scope): summary`, ending with the `Co-Authored-By` trailer from CLAUDE.md. Stage files by name. `composer.json` is never staged (run.sh rewrite).

## Review focus

- **A stored section missing a key, such as after a schema change.** It falls back to global, it does not fatal, and the SPA never sees it. Verified in Phase 1 Task 1 Step 2.
- **Custom CSS with `</style` in a layer** is refused with 400 on save, and dropped from an import. Verified in Phase 1 Task 3 Step 4 and by the accepted test.
- **A disabled section in the layer while global has it on.** Its variables must disappear on the storefront and in the live canvas. Verified in Phase 1 Task 3 Step 3 and Phase 2 Task 2.
- **Free with a stored layer**, for example after a downgrade, prints global and exposes no meta. Verified in Phase 1 Task 2 Step 3.
- **A dirty editor** before "Edit global design ↗" asks first. Verified in Phase 2 Task 4.

## Phases

| Phase | Delivers (user-visible) | Exit criterion (exact check) | Status |
|---|---|---|---|
| 1 | A layout's layer, set through `wp/v2` or import, changes that layout's storefront, preview and editor canvas. Free ignores it. | `/opt/homebrew/opt/php@8.4/bin/php vendor/bin/phpunit --filter LayoutImportDesignTest` passes, and the Phase 1 exit walkthrough shows the overridden primary on the layout's preview link. | done 2026-10-09 |
| 2 | The Design toolbar button and sidebar in the editor, with live canvas, revert confirm, the global link and the Free teaser. | Spec acceptance criterion 13 walkthrough passes on yaycheckout.test. | done 2026-10-09 |

## Interface contract

- **`Layout::META_DESIGN = '_yaycheckout_design'`** and `public array $design = []` on `Models\Layout`. The array is read through `Design::project_layer()`. Phase 1 Task 2 produces them. Phase 1 Tasks 3–4 consume them.
- **`Design::project_layer( $raw ): array`** (static). It keeps a section only when every schema key of it is valid, keeps `custom_css` only when it is a non-empty string that passes `sanitize_custom_css()`, and drops the rest silently. Phase 1 Task 1. Consumed by Phase 1 Tasks 2–4.
- **`Design::validate_layer( array $input )`** (static), returning `array|\WP_Error`. On error the message names the first failing path, using the `invalid( 'colours.primary' )` message or the CSS message. On success it returns the same shape as `project_layer`. Phase 1 Task 1. Consumed by Phase 1 Task 3.
- **`Design::with_layer( array $layer ): Design`** returns a clone. Each layer section replaces the global section whole. `custom_css` becomes the global CSS, then `"\n"`, then the layer CSS when the layer has CSS, unless the two together pass `CUSTOM_CSS_MAX_BYTES`, when the global CSS alone is kept; a `wp/v2` save that would pass it answers 400. `updated_at` is unchanged. Phase 1 Task 1. Consumed by Phase 1 Task 3.
- **`DesignRepository::for_layout( ?Layout $layout ): Design`** returns `get()`, passed through `apply_filters( 'yaycheckout_design_for_layout', $design, $layout )` when `$layout` is not null. Phase 1 Task 2.
- **Filter `yaycheckout_design_for_layout( Design $design, Layout $layout ): Design`**, hooked by Pro to `$design->with_layer( $layout->design )`. Phase 1 Tasks 2–3.
- **`CheckoutBaseStyles::enqueue( bool $with_custom_css, ?Layout $layout = null ): void`**. Phase 1 Task 2.
- **`LayoutRepository::import( string $title, string $content, array $rules = [], array $design = [] ): Layout`** and the private `insert_draft(…, array $design = [])`, which writes `META_DESIGN` only when `$design` is not `[]`. Phase 1 Tasks 2 and 4.
- **`LayoutImportReview::from_envelope()`** returns `{ title, content, rules, design }`, where `design` is the file's raw value or `null`. Phase 1 Task 4.
- **REST meta field `meta._yaycheckout_design`** (object) on `wp/v2/yay_checkout_layout`, Pro only. Phase 1 Task 3. Consumed by Phase 2 Tasks 3–4.
- **Filter `yaycheckout_editor_data( array $data ): array`**, applied to `window.yayCheckoutBlocks`. Pro adds `design: { tokens: <global tokens, wire shape>, schema: Design::schema(), font_families: Design::FONT_FAMILIES }`. Phase 2 Task 1. Consumed by Phase 2 Tasks 2–3.
- **`designVars( tokens ): Record<string, string>`** in `packages/blocks/src/shared/design-vars.ts`. It mirrors `DesignStyles::vars()` and `DesignStyles::INPUT_HEIGHTS`: the same names without the `--yaycheckout-` prefix and the same values, with `initial` for the variables of a disabled section. Phase 2 Task 2. Consumed by Phase 2 Task 3.
