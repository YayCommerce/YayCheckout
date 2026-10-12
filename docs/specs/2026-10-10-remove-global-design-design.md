# Remove Global Design Design

**Status:** accepted 2026-10-10
**Related:** [Layout templates design](2026-10-10-layout-templates-design.md), whose Out list deferred this removal to its own spec; [the rejected override-layer record](../decisions/2026-10-09-layout-design-override-layer.md)

## Goal

A layout owns its whole design: the merchant styles each checkout layout in the editor's Layout design sidebar, and there is no global Design page or global design underneath it. Custom CSS that must reach every checkout moves to a site-wide setting in Settings → Advanced.

## Context

- Today two designs combine on every checkout:
  - The global design is the option `yaycheckout_design`, modelled by `includes/Models/Design.php`. `includes/Repositories/DesignRepository.php` reads and writes it, `includes/Controllers/DesignRestController.php` serves `GET/POST /yaycheckout/v1/design`, and the SPA Design page (`apps/admin/src/pages/design/`) edits it.
  - The per-layout design is the post meta `_yaycheckout_design` (`Layout::META_DESIGN`, `includes/Models/LayoutDesign.php`). Each of its sections (`colours`, `typography`, `inputs`) is either a full array or `null`, and `null` follows the global section. It also has a `custom_css` string.
- `Renderer\DesignStyles::css( Design, LayoutDesign, bool )` prints `vars( $layout->tokens_over( $design->tokens ) )`: the global tokens with each non-null layout section swapped in. A section prints only while its `enabled` flag is true. On the storefront, the global custom CSS prints first, then the layout's.
- Callers of the global design:
  - `Renderer\CheckoutBaseStyles::enqueue()` reads `DesignRepository::get()`. It is called by `CheckoutPage` (the storefront) and `Engine\Admin\LayoutEditorCanvas` (the editor canvas, which falls back to `LayoutDesign::none()`).
  - `Blocks\BlockRegistry::editor_design_data()` prints the global `tokens` into `window.yayCheckoutBlocks.design`.
  - `Helpers\LocalizeHelper` puts `design` into `window.yayCheckoutAdmin`.
- `LayoutDesign` borrows from `Design`:
  - `schema()`, with colours widened to `color`/`fill`;
  - `project_section()` and `is_valid()`, including the `color`/`fill` branches;
  - `is_gradient()` and `sanitize_custom_css()`;
  - `CUSTOM_CSS_MAX_BYTES` (50000) and `FONT_FAMILIES`;
  - the four colour and gradient patterns and `GRADIENT_MAX_LENGTH` (1000).
- The editor sidebar `packages/blocks/src/checkout/layout-design.tsx`:
  - It offers "Global design / Custom" per section. Its parts are `Mode`, `modeOf()`, `seed()` (which copies the global section on switching to Custom) and `effectiveTokens()`.
  - Its help copy says "Override the global design for this layout only. Global design follows the Design page."
- `packages/blocks/src/shared/design-css.ts` mirrors `DesignStyles::vars()` / `css()` for the canvas.
- Every shipped template's `design.json` (today only `templates/layouts/classic-two-column/design.json`) already sets all three sections with `enabled: true`. So layouts created from templates, Blank, Reset and the wizard already ignore global tokens, but still get the global custom CSS.
- Nothing in design is gated to Pro. No tests cover `Design`, `DesignRepository`, `DesignStyles` or the Design page. `tests/Integration/LayoutTemplateCatalogueTest.php` covers `LayoutDesign` and `design.json`.
- The plugin has not shipped to merchants: only dev and test sites hold a `yaycheckout_design` row.

## Scope

**In:**
- Delete the global design:
  - `Models\Design`, `Repositories\DesignRepository` and `Controllers\DesignRestController`, with their registration in `Engine\RestAPI`;
  - the SPA Design page, its route, its nav tab, and the modules only it uses.
- `Models\LayoutDesign` is the only design model and owns the schema, validation and CSS rules it borrowed from `Design`.
- Drop every section's `enabled` key and the `null` section state. Every token accepts an empty value, and an empty token prints nothing.
- The editor's Layout design sidebar always shows each section's controls, and each control can be cleared to empty.
- A site-wide custom CSS setting, `advanced.custom_css`, in Settings → Advanced, for Free and Pro. It prints on every checkout before the layout's own CSS.
- Update `classic-two-column/design.json`, the TypeScript mirrors, docs and comments, and add the decision record.

**Out:**
- Any migration of `yaycheckout_design` values into layouts or into the new setting, and any upgrade routine that deletes the option. The plugin has not shipped.
- Changing the uninstall routine: its `yaycheckout\_%` wildcard already removes the option.
- New tokens, a Pro gate on design, and printing custom CSS in the editor canvas.
- Exporting or importing the site-wide CSS with a layout.
- Fonts loading (the existing `TODO(fonts)` in `DesignStyles`).

## Acceptance criteria

1. The header nav has no Design tab, and `router.tsx` has no `design` route. `GET /yaycheckout/v1/design` returns 404 (`rest_no_route`).
2. `git grep -nE "DesignRepository|DesignRestController|Models\\\\Design;|OPTION_DESIGN|['\"]yaycheckout_design['\"]|/design\.(api|queries|schema|helper)|PreviewPane|FontFamilySelect" -- includes apps packages templates` prints nothing. The leading quote keeps the meta key `_yaycheckout_design` out of the match.
3. `LayoutDesign::from_meta( $value )->to_meta()` always returns exactly `colours`, `typography`, `inputs` and `custom_css`, with the token keys in the table below and no `enabled` key. For `$value` = `[]`, `null`, `[ 'colours' => null ]`, a section holding `enabled` or an unknown key, or a token of the wrong type, each such token reads as its empty value.
4. Token schema (`LayoutDesign::schema()`), all defaults empty:

   | Token | Type | Allowed | Empty |
   |---|---|---|---|
   | `colours.primary`, `colours.text`, `colours.border` | `color` | hex, or a theme preset (`var(--wp--preset--color--<slug>)`) | `''` |
   | `colours.background` | `fill` | a `color`, or a gradient within `GRADIENT_MAX_LENGTH` (1000) | `''` |
   | `typography.font_family` | `enum` | the `FONT_FAMILIES` keys | `''` |
   | `typography.base_size` | `int` | 10–24 | `null` |
   | `inputs.style` | `enum` | `boxed`, `underline` | `''` |
   | `inputs.size` | `enum` | `small`, `medium`, `large` | `''` |
   | `inputs.radius` | `int` | 0–24 | `null` |
5. `DesignStyles::css()` prints a `--yaycheckout-*` property only for a token that isn't empty:
   - `color-primary`, `color-text` and `color-border`, one for each colour that is set;
   - `color-background`, or `background-gradient` when the background is a gradient;
   - `font-family` when `font_family` isn't `''`;
   - `font-size-base` when `base_size` isn't `null`;
   - `input-height` when `size` is set;
   - `input-border-width` and `input-padding-inline` when `style` is set;
   - `input-radius` when `radius` isn't `null`, and `0px` whenever the style is `underline`.

   A layout whose tokens are all empty prints no `--yaycheckout-*` property and no page rule.
6. On the storefront, `advanced.custom_css` prints before the layout's `custom_css`. Each is checked with `LayoutDesign::sanitize_custom_css()` on print and dropped alone when it fails. The editor canvas prints neither.
7. `POST /yaycheckout/v1/settings` with `advanced.custom_css` over 50000 bytes, or failing `sanitize_custom_css()`, returns 400 with that method's message and stores nothing. A valid value round-trips through `GET /settings`, with the default `''`.
8. Settings → Advanced shows a Custom CSS textarea that saves with the other settings. Its description reads "Printed on every checkout, before each layout's own custom CSS. Not shown in the editor."
9. In the editor's Layout design sidebar:
   - each of Colours, Typography and Inputs always shows its controls;
   - no string says "Global design";
   - every control can be set back to empty:
     - a colour's clear;
     - "Theme default" for Font, Style and Size;
     - an empty field for Base size and Radius.

   Clearing a token removes its property from the canvas preview at once.
10. `templates/layouts/classic-two-column/design.json` holds the same values as today, without the three `enabled` keys. `composer test` passes, `LayoutTemplateCatalogueTest` included.
11. End-to-end verification:
    1. Open wp-admin. There is no Design tab.
    2. Create a layout from Classic two-column and open it in the editor. The canvas looks as it does today: `#f0f0f1` page and `#3858e9` primary.
    3. Clear Primary in the sidebar and save. The canvas and the storefront button use the theme's colour, and no `--yaycheckout-color-primary` is printed.
    4. In Settings → Advanced, save `.yaycheckout-checkout{outline:1px solid red}` as Custom CSS. Load the checkout: that rule prints before the layout's custom CSS.
    5. Place an order with a test gateway. It succeeds.

## Constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free and Pro behave the same. Design and both CSS fields stay ungated, and nothing here may reference `YayCheckout\Pro` outside `boot_pro()` (`scripts/check-pro-boundary.sh`).
- Wire casing is snake_case, and the SPA's zod wire schemas stay strict.
- Never hand-edit `assets/dist/`. Rebuild the blocks with `pnpm build:blocks`.
- The meta key `_yaycheckout_design` and the option `yaycheckout_settings` keep their names.
- Judge `pnpm --filter @yaycheckout/admin-page lint` by "no new failures". On `dev/edd` it already fails: 4 eslint warnings, and prettier failing on `src/lib/utils.ts`, `src/styles/custom-header.css` and `tsconfig.node.json`.

## Approach

**Chosen:** A — fold the global model into `LayoutDesign`.

**Architecture:** `LayoutDesign` is the single design model. It holds the schema, projection, validation and CSS-safety rules that `Design` used to own, and every section in it is always a full array. `DesignStyles` builds CSS from one `LayoutDesign`, plus the site CSS string read from settings. Nothing reads a global design, so the option, repository, route and page are deleted rather than left behind. The site-wide CSS becomes an ordinary setting, so it reuses the settings schema, REST route, SPA form and Save footer.

**Components:**
- `includes/Models/LayoutDesign.php`:
  - `schema()` is the table above, with no `enabled`;
  - it owns the patterns, `GRADIENT_MAX_LENGTH`, `CUSTOM_CSS_MAX_BYTES`, `FONT_FAMILIES`, `is_gradient()`, `sanitize_custom_css()` and the private projection and validation;
  - `from_meta()` and `to_meta()` always carry every section;
  - `rest_schema()` requires every section and allows each token's empty value;
  - `none()` and `tokens_over()` are removed.
- `includes/Renderer/DesignStyles.php`: `css( LayoutDesign $design, string $site_css, bool $with_custom_css ): string`. `vars()` takes the layout's sections and prints only tokens that aren't empty.
- `includes/Renderer/CheckoutBaseStyles.php`: `enqueue( bool $with_custom_css, LayoutDesign $design )` reads `advanced.custom_css` from `SettingsRepository` when `$with_custom_css` is true.
- `includes/Renderer/CheckoutPage.php`: passes the rendered layout's design (unchanged call shape).
- `includes/Engine/Admin/LayoutEditorCanvas.php`: passes the edited layout's design, or `LayoutDesign::from_meta( [] )` when no layout is loaded.
- `includes/PostTypes/LayoutPostType.php`: the meta default is `LayoutDesign::from_meta( [] )->to_meta()`.
- `includes/Blocks/BlockRegistry.php`: `editor_design_data()` drops `tokens`, and takes `fontFamilies` and `customCssMaxBytes` from `LayoutDesign`.
- `includes/Helpers/LocalizeHelper.php`: drops `design`.
- `includes/Models/Settings.php`:
  - `schema()` gains `advanced.custom_css` as `[ 'type' => 'css', 'default' => '' ]`;
  - `is_valid()` accepts a `css` value when `LayoutDesign::sanitize_custom_css()` returns it unchanged;
  - `sanitize()`'s 400 for that key carries the method's message.
- `includes/Pro/Services/LayoutImportReview.php`: its comment changes from "follows the global design" to "imports as empty". Reading the file still goes through `LayoutDesign::from_meta()`.
- Delete:
  - `includes/Models/Design.php`, `includes/Repositories/DesignRepository.php`, `includes/Controllers/DesignRestController.php`, and the controller's line in `includes/Engine/RestAPI.php`;
  - `apps/admin/src/pages/design/` (the page and its 9 components), `components/shared/PreviewPane.tsx` and `components/shared/FontFamilySelect.tsx`;
  - `lib/api/design.api.ts`, `lib/queries/design.queries.ts`, `lib/schema/design.schema.ts` and `lib/helpers/design.helper.ts`.
- `apps/admin/src/router.tsx` and `components/layout/HeaderMenu.tsx`: drop the Design route and tab. `apps/admin/src/types/localize.type.ts`: drop `design`.
- `apps/admin/src/lib/schema/settings.schema.ts`: `advanced` gains `custom_css: z.string()` (wire) and `customCss` (form). `pages/settings/components/AdvancedTab.tsx` gains the Custom CSS row:
  - a monospace `Textarea`, 8 rows, `maxLength` 50000, placeholder `.yaycheckout-checkout { }`;
  - the description from criterion 8.
- `packages/blocks/src/checkout/layout-design.tsx`:
  - drops `Mode`, `modeOf()`, `seed()`, `effectiveTokens()` and the toggle;
  - each section always shows its controls;
  - Font, Style and Size gain a "Theme default" option (`''`);
  - Base size and Radius allow an empty field (`null`);
  - the canvas uses `designCss( override )`;
  - help copy: "This layout's design. An empty value follows your theme.", and for Custom CSS: "Printed on the checkout after the site-wide custom CSS (Settings → Advanced). Not shown in the editor."
- `packages/blocks/src/shared/design-css.ts`: mirrors the new `vars()` / `css()` rules.
- `packages/blocks/src/shared/editor-data.ts`: `EditorDesign` drops `tokens`, and its comment says it describes the layout design's rules.
- `templates/layouts/classic-two-column/design.json`: drop the three `enabled` keys.

**Data and storage:**
- `_yaycheckout_design` post meta always holds `{ colours: { primary, text, background, border }, typography: { font_family, base_size }, inputs: { style, size, radius }, custom_css }`.
- The new key `advanced.custom_css` (string, default `''`) goes in the option `yaycheckout_settings`.
- The option `yaycheckout_design` is no longer read or written. There is no migration and no deletion on upgrade, and uninstall's wildcard still removes it.
- Stored layout meta with `null` sections or `enabled` keys reads as the empty defaults, and is rewritten in the new shape on its next save.

**Integration points:**
- The route `GET/POST /yaycheckout/v1/design` is removed.
- `GET/POST /yaycheckout/v1/settings` gains `advanced.custom_css`.
- `window.yayCheckoutAdmin` loses `design`, and `window.yayCheckoutBlocks.design` loses `tokens`.
- `wp/v2` saves of `_yaycheckout_design` validate against the new `rest_schema()`.
- Export (`includes/Pro/Controllers/LayoutExportRestController.php`) carries `to_meta()` unchanged. Import reads through `from_meta()`.

**UI:**
- wp-admin SPA: the Design tab is removed, and Settings → Advanced gains Custom CSS.
- Block editor: the Layout design sidebar as above.
- Storefront: unchanged except that empty tokens and the site CSS behave as the criteria state.

**Edge cases and failure modes:**
- An old layout or import file with `null` sections, `enabled` keys or unknown keys reads as empty tokens and shows the theme. It never fails to load.
- A `wp/v2` save with a token of the wrong type gets a 400 from `rest_schema()`, as today.
- An all-empty layout prints no tokens and still publishes: design never affects `publish_error()`.
- A gradient background fills only the page. Inputs and rows keep their solid fallback.
- Underline style forces the radius to `0px`, whatever the radius value.
- If a stored site CSS value or layout CSS value fails the check on print, only that one is dropped.
- A Free site behaves exactly like Pro for design and both CSS fields.

**Decision record:**
- The implementing PR adds `docs/decisions/YYYY-MM-DD-layout-owns-its-design.md`, titled "A layout owns its whole design; there is no global design". It records the rules of this Approach and what was turned down: approaches B and C, keeping `null` sections as Theme default, and a migration.
- `docs/decisions/2026-10-09-layout-design-override-layer.md` is already Rejected and isn't edited.
- `docs/decisions/2026-10-08-checkout-owns-base-styles.md` stays accepted. Its "custom CSS is the escape hatch" holds through both CSS fields, and its `PreviewPane` mirror note becomes history.
- The PR updates these docs:
  - `docs/architecture.md` lines 18, 39, 40, 41, 45, 62, 85 and 100;
  - `docs/conventions.md` lines 14–17 and 23;
  - `.claude/rules/php-standards.md:36` and `CLAUDE.md:55`, which now mean `LayoutDesign::schema()` by "design schema";
  - the comment in `assets/css/checkout-base.css:5`.

## Alternatives considered

| Option | Why rejected |
|---|---|
| B: keep `Design` as a rules-only class (`DesignRules`) and delete only storage, route and page | Leaves two design classes where only one has a reason to exist. |
| C: keep `enabled`, always true and hidden | Leaves a field that is always true, which the sidebar, import and docs would keep explaining. |
| Keep `null` sections as "Theme default" with a Theme default / Custom toggle | The merchant chose one mode: each section is always custom, and an empty token prints nothing. |
| Migrate `yaycheckout_design` into layouts and the new setting | The plugin has not shipped. No merchant data depends on the global design. |
| Drop global custom CSS with no replacement | The merchant wants CSS that reaches every checkout. Settings → Advanced keeps that. |
| Pro-only site CSS, or putting it in Settings → General | Custom CSS has always been Free. Advanced is where the other technical options live. |

## Rollout

None. The plugin has not shipped, so there is no upgrade path, release note or flag. A leftover `yaycheckout_design` row on a dev site is ignored, and uninstall removes it.

## Open questions

None.
