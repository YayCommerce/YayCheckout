# Remove Global Design Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** active
**Goal:** A layout owns its whole design: the global design (option, model, repository, `/design` route, Design page) is deleted, every layout token can be empty and then prints nothing, and site-wide custom CSS becomes the setting `advanced.custom_css`.
**Spec:** [docs/specs/2026-10-10-remove-global-design-design.md](../specs/2026-10-10-remove-global-design-design.md)
**Architecture:** `LayoutDesign` absorbs the rules `Design` owned and loses `enabled` and `null` sections (spec approach A). `DesignStyles::css()` then builds CSS from one layout plus a site CSS string. The site CSS reuses the settings schema, route and form, so it adds no new storage or route.
**Touches:**
- PHP:
  - `includes/Models/LayoutDesign.php` (shared: read by `DesignStyles`, `LayoutPostType`, `BlockRegistry`, `Layout`, `LayoutRepository`, `LayoutTemplateCatalogue`, Pro `LayoutExportRestController`, `LayoutImportRestController`, `LayoutImportReview`, and `tests/Integration/LayoutTemplateCatalogueTest.php`)
  - `includes/Renderer/{DesignStyles,CheckoutBaseStyles}.php`
  - `includes/Engine/Admin/LayoutEditorCanvas.php`, `includes/PostTypes/LayoutPostType.php`, `includes/Blocks/BlockRegistry.php`, `includes/Helpers/LocalizeHelper.php`, `includes/Engine/RestAPI.php`
  - `includes/Models/Settings.php` (shared: the `/settings` route, `LocalizeHelper` and every reader of `SettingsRepository`)
  - `includes/Pro/Services/LayoutImportReview.php` (comment only)
  - deleted: `includes/Models/Design.php`, `includes/Repositories/DesignRepository.php`, `includes/Controllers/DesignRestController.php`
- Template: `templates/layouts/classic-two-column/design.json`
- Blocks: `packages/blocks/src/checkout/layout-design.tsx`, `packages/blocks/src/checkout/import-export.tsx` (comment), `packages/blocks/src/shared/{design-css,editor-data}.ts` (shared: `editor-data.ts` is read by every block through `editorData`)
- SPA:
  - `apps/admin/src/router.tsx`, `components/layout/HeaderMenu.tsx`, `types/localize.type.ts`
  - `lib/schema/settings.schema.ts`, `lib/api/settings.api.ts`, `pages/settings/components/AdvancedTab.tsx`
  - deleted: `pages/design/`, `components/shared/{PreviewPane,FontFamilySelect}.tsx`, `lib/api/design.api.ts`, `lib/queries/design.queries.ts`, `lib/schema/design.schema.ts`, `lib/helpers/design.helper.ts`
- Docs:
  - `docs/architecture.md`, `docs/conventions.md`, `.claude/rules/php-standards.md`, `CLAUDE.md`
  - `assets/css/checkout-base.css` (comment)
  - the new `docs/decisions/2026-10-10-layout-owns-its-design.md`
  - `docs/plans/2026-10-09-layout-design-override.md` (Status line)

**Tests:** none new. CLAUDE.md's test rule doesn't cover design CSS, which changes how a checkout looks, not whether it works. `tests/Integration/LayoutTemplateCatalogueTest.php` must keep passing unchanged: `design.json` round-trips through `from_meta()->to_meta()`.

## Global constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free and Pro behave the same. Design and both CSS fields stay ungated, and nothing here may reference `YayCheckout\Pro` outside `boot_pro()`. `composer lint` runs `scripts/check-pro-boundary.sh`.
- Wire casing is snake_case. The SPA converts only in `apps/admin/src/lib/api/<resource>.api.ts` and parses with strict wire schemas.
- The meta key `_yaycheckout_design` (`Layout::META_DESIGN`) and the option `yaycheckout_settings` keep their names.
- No migration, and nothing deletes `yaycheckout_design` on upgrade.
- Never hand-edit `assets/dist/`. Rebuild the blocks with `pnpm build:blocks`.
- `pnpm --filter @yaycheckout/admin-page lint` is judged by "no new failures". On `dev/edd` it already fails: 4 eslint warnings, and prettier failing on `src/lib/utils.ts`, `src/styles/custom-header.css` and `tsconfig.node.json`.
- Git:
  - All work goes on `trace/feat/remove-global-design`, stacked on `trace/feat/layout-templates`. It lands as one squash PR, based on `trace/feat/layout-templates` until that PR merges, then retargeted to `dev/edd`.
  - Commit messages are Conventional Commits ending with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`.
  - Stage files by name.
- Run pnpm on Node 22 (`nvm use`).

## Review focus

- **An old layout or import:** a `null` section, a leftover `enabled`, an unknown key or a wrong-typed token reads as the empty value and never fails to load. Task 1 Step 3.
- **Empty `int` tokens are `null`, not `0`:** `radius: 0` still prints `0px`, while `radius: null` prints nothing. Task 1 Step 3 and Task 2 Step 4.
- **Underline with a radius:** `input-radius` is `0px` whatever `radius` is, even `null`. Task 1 Step 3.
- **The canvas matches the storefront:** `design-css.ts` and `DesignStyles::vars()` print the same text for the same tokens, including empty ones. Task 2 Step 4.
- **Site CSS and layout CSS:** each is dropped alone when it fails its check on print, and only the storefront prints them. Task 4 Step 5.
- **The settings route:** a site CSS value over 50000 bytes, or holding `</style`, gets a 400 with `sanitize_custom_css()`'s message and stores nothing. Task 4 Step 4.

---

### Task 1: `LayoutDesign` is the only design model, and the storefront prints it alone

**Files:**
- Modify: `includes/Models/LayoutDesign.php`
- Modify: `includes/Renderer/DesignStyles.php`, `includes/Renderer/CheckoutBaseStyles.php`
- Modify: `includes/Engine/Admin/LayoutEditorCanvas.php:89`, `includes/PostTypes/LayoutPostType.php:89`
- Modify: `includes/Blocks/BlockRegistry.php:205-226` (constants only, in this task)
- Modify: `includes/Pro/Services/LayoutImportReview.php` (the "follows the global design" comment becomes "imports as empty")
- Modify: `templates/layouts/classic-two-column/design.json`

**Interfaces:**
- Produces:
  - `LayoutDesign::schema(): array`: the spec's token table, with every default empty (`''`, or `null` for `int`) and no `enabled` key. `int` rules keep `min`/`max` and also accept `null`. The `inputs.style` enum is `[ '', 'boxed', 'underline' ]`, `inputs.size` is `[ '', 'small', 'medium', 'large' ]`, and `typography.font_family` is `array_keys( LayoutDesign::FONT_FAMILIES )`, which includes `''`.
  - Constants, all public on `LayoutDesign`, copied from `Design` with the same values. `Design` keeps serving the Design page until Task 3 deletes it:
    - `HEX_COLOR_PATTERN`, `PRESET_COLOR_PATTERN`, `GRADIENT_PATTERN`, `PRESET_GRADIENT_PATTERN`;
    - `GRADIENT_MAX_LENGTH = 1000`, `CUSTOM_CSS_MAX_BYTES = 50000`;
    - `FONT_FAMILIES`.
  - Statics copied from `Design`:
    - `LayoutDesign::is_gradient( string $fill ): bool`;
    - `LayoutDesign::sanitize_custom_css( string $css )`, which returns `string|\WP_Error` with the same messages.
    - Projection and validation become private: `project_section()` and `is_valid()` (the `hex_color` type is dropped; `color` and `fill` stay).
  - `public array $sections`: section => full token array, never `null`. `public string $custom_css`.
  - `LayoutDesign::from_meta( $value ): self`: every section is always present, and anything missing or invalid reads as its empty default. `to_meta(): array` returns exactly `colours`, `typography`, `inputs` and `custom_css`.
  - `LayoutDesign::rest_schema(): array`: each section is `type: object` (no `null`), and every token is required. `int` is `[ 'integer', 'null' ]`, and `enum` includes `''`.
  - Removed: `LayoutDesign::none()` and `tokens_over()`.
  - `DesignStyles::css( LayoutDesign $design, string $site_css, bool $with_custom_css ): string`.
  - `CheckoutBaseStyles::enqueue( bool $with_custom_css, LayoutDesign $design ): void` passes `''` as the site CSS until Task 4.

- [ ] **Step 1: Rewrite `includes/Models/LayoutDesign.php` to the Produces block**

  It no longer `use`s `Design`. Its class docblock says it is the only design model.

  Until Task 2 lands, the editor canvas prints no tokens, because `design-css.ts` still checks `enabled`. The storefront is already right. The old sidebar still loads, but saving a section set to Global design is refused, since `rest_schema()` no longer allows `null`. Run Task 2 next.

- [ ] **Step 2: Rewrite `DesignStyles` and `CheckoutBaseStyles`**

  `DesignStyles::vars( array $sections ): array` prints only tokens that aren't empty, as spec criterion 5 lists:
  - `input-radius` is `0px` when the style is `underline`, otherwise `{radius}px` when `radius` isn't `null`;
  - `input-border-width` / `input-padding-inline` are `0 0 1px` / `0` for underline and `1px` / `12px` for boxed, and print only when `style` isn't `''`;
  - `input-height` prints only when `size` isn't `''`;
  - `font-size-base` prints only when `base_size` isn't `null`.

  Custom CSS order when `$with_custom_css`: `$site_css`, then `$design->custom_css`, each through `LayoutDesign::sanitize_custom_css()` and dropped alone on a `WP_Error` or when blank. Update the class docblock and the mirror notes: the `PreviewPane.tsx` mentions go, and `design-css.ts` stays.

  `CheckoutBaseStyles` stops using `DesignRepository`. `LayoutEditorCanvas:89` passes `LayoutDesign::from_meta( [] )` when no layout is loaded, and `LayoutPostType:89` defaults the meta to `LayoutDesign::from_meta( [] )->to_meta()`. In `BlockRegistry::editor_design_data()`, `fontFamilies` and `customCssMaxBytes` come from `LayoutDesign`. `tokens` stays until Task 3.

  In `classic-two-column/design.json`, delete the three `enabled` keys and keep every other value and its order.

- [ ] **Step 3: Verify the model and the CSS with WP-CLI**

  Run `wp eval` (on Local, through the per-site socket from `docs/known-issues.md`):
  - `from_meta( [] )`, `from_meta( null )`, `from_meta( [ 'colours' => null ] )`, `from_meta( [ 'colours' => [ 'enabled' => true, 'primary' => 5, 'x' => 1 ] ] )`: expect `to_meta()` to have exactly the 4 top keys and the spec's token keys, with no `enabled`, every token empty, and `custom_css` `''`.
  - `css( from_meta( [] ), '', true )`: expect `''`.
  - `css( from_meta( [ 'inputs' => [ 'style' => 'underline', 'size' => '', 'radius' => null ] ] ), '', false )`: expect `--yaycheckout-input-radius: 0px`, `input-border-width: 0 0 1px` and `input-padding-inline: 0`, and no `input-height`.
  - `css( from_meta( [ 'inputs' => [ 'style' => 'boxed', 'size' => 'medium', 'radius' => 0 ] ] ), '', false )`: expect `input-radius: 0px`, `input-height: 40px`, `input-border-width: 1px` and `input-padding-inline: 12px`.
  - `css( from_meta( [ 'colours' => [ 'background' => 'linear-gradient(red, blue)' ] ] ), '', false )`: expect `background-gradient` and no `color-background`.

- [ ] **Step 4: Run the checks**

  - `composer lint -- <the PHP files above>`: expect "✓ Pro boundary intact" and phpcs with no errors.
  - `composer test`: expect OK, `LayoutTemplateCatalogueTest` included.

- [ ] **Step 5: Commit**

  Stage the files above. Message: `refactor(design): make LayoutDesign the only design model, with empty tokens and no enabled`.

### Task 2: The editor's Layout design sidebar edits the layout's design alone

**Files:**
- Modify: `packages/blocks/src/checkout/layout-design.tsx`
- Modify: `packages/blocks/src/shared/design-css.ts`
- Modify: `packages/blocks/src/shared/editor-data.ts`
- Modify: `packages/blocks/src/checkout/import-export.tsx:31` (the comment "every section null when the file has none" becomes "every token empty when the file has none")

**Interfaces:**
- Consumes: Task 1's `schema()` shape and the `rest_schema()` save rules.
- Produces:
  - `LayoutDesignMeta = { colours: SectionTokens; typography: SectionTokens; inputs: SectionTokens; custom_css: string }`, with no `null` sections. `SectionTokens = Record< string, string | number | null >`.
  - `designCss( tokens: DesignTokens, fontFamilies: Record< string, string > ): string` mirrors Task 1's `vars()`/`css()` exactly.
  - `EditorDesign` loses `tokens`. The `DesignRule` variant `int` gets `default: null`, and `hex_color` and `bool` are dropped.

- [ ] **Step 1: Update `editor-data.ts` and `design-css.ts`**

  The `vars()` comment says it mirrors `DesignStyles::vars()`, and `isGradient()` says it mirrors `LayoutDesign::is_gradient()`.

- [ ] **Step 2: Rewrite the sidebar in `layout-design.tsx`**

  - Delete `Mode`, `modeOf()`, `seed()`, `effectiveTokens()`, `NONE` and the `ToggleGroupControl`. `normalize()` fills each section from `design.schema` defaults.
  - Each `SectionPanel` always renders its controls.
  - `ENUM_LABELS[ '' ]` is `__( 'Theme default', 'yaycheckout' )`.
  - The `int` `RangeControl` uses `allowReset`, `value={ null === value ? undefined : value }`, and `onChange( next ?? null )`.
  - The canvas uses `useCanvasDesign( designCss( override, design.fontFamilies ), design.styleId )`.
  - Copy:
    - the panel help is "This layout's design. An empty value follows your theme.";
    - the Custom CSS help is "Printed on the checkout after the site-wide custom CSS (Settings → Advanced). Not shown in the editor.";
    - no string contains "Global design".
  - `customCssError()` says it mirrors `LayoutDesign::sanitize_custom_css()`.

- [ ] **Step 3: Build**

  Run `pnpm build:blocks`. Expect "compiled successfully".

- [ ] **Step 4: Verify in the editor**

  1. Create a layout from Classic two-column, then open it at `/wp-admin/post.php?post=<id>&action=edit`.
  2. Open the Layout design sidebar. Expect:
     - Colours, Typography and Inputs each show their controls, with no toggle;
     - the canvas has a `#f0f0f1` background, and `#3858e9` as `--yaycheckout-color-primary` in the inline style `yaycheckout/style/checkout-inline-css`.
  3. Clear Primary. Expect the inline style to lose `--yaycheckout-color-primary` at once.
  4. Set Radius to 0. Expect `input-radius: 0px`.
  5. Reset Radius. Expect no `input-radius`.
  6. Save. Expect the save to succeed and `wp post meta get <id> _yaycheckout_design` to show `"primary":""` and `"radius":null` with no `enabled`.
  7. Compare the editor's inline `:root` block with the one from Task 1's `DesignStyles::css( from_meta( <that meta> ), '', false )` via `wp eval`. Expect identical text.
  8. Delete the layout.

- [ ] **Step 5: Commit**

  Stage the files above. Message: `feat(editor): edit the layout's whole design, with empty tokens instead of Global design`.

### Task 3: Delete the global design

**Files:**
- Delete:
  - `includes/Models/Design.php`, `includes/Repositories/DesignRepository.php`, `includes/Controllers/DesignRestController.php`
  - `apps/admin/src/pages/design/` (whole folder)
  - `apps/admin/src/components/shared/PreviewPane.tsx`, `apps/admin/src/components/shared/FontFamilySelect.tsx`
  - `apps/admin/src/lib/api/design.api.ts`, `apps/admin/src/lib/queries/design.queries.ts`, `apps/admin/src/lib/schema/design.schema.ts`, `apps/admin/src/lib/helpers/design.helper.ts`
- Modify:
  - `includes/Engine/RestAPI.php`: drop the `DesignRestController` lines.
  - `includes/Blocks/BlockRegistry.php`: drop `tokens`, the `DesignRepository` and `Design` imports, and "the global design" in the docblock.
  - `includes/Helpers/LocalizeHelper.php`: drop `'design'`.
  - `apps/admin/src/types/localize.type.ts`: drop `design`.
  - `apps/admin/src/router.tsx` and `apps/admin/src/components/layout/HeaderMenu.tsx`: drop the Design route and tab.
- Modify (docs): `docs/architecture.md` lines 18, 39, 40, 41, 45, 85 and 100; `docs/conventions.md` lines 14–17 and 23; `.claude/rules/php-standards.md:36`; `CLAUDE.md:55`. Each now describes one layout-owned design, with `LayoutDesign::schema()` as the design schema.

**Interfaces:**
- Consumes: Task 1, which has removed every use of `Design` and `DesignRepository` outside these files.
- Produces: `GET/POST /yaycheckout/v1/design` is removed. `window.yayCheckoutAdmin` has no `design`, and `window.yayCheckoutBlocks.design` has no `tokens`.

- [ ] **Step 1: Delete and modify the files above**

- [ ] **Step 2: Run the stale-reference grep**

  Run spec criterion 2's command:

  ```
  git grep -nE "DesignRepository|DesignRestController|Models\\\\Design;|OPTION_DESIGN|['\"]yaycheckout_design['\"]|design\.(api|queries|schema|helper)|PreviewPane|FontFamilySelect" -- includes apps packages templates
  ```

  Expect no output.

- [ ] **Step 3: Run the checks**

  - `composer lint`: clean.
  - `composer test`: OK.
  - `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`: exit 0.
  - `pnpm --filter @yaycheckout/admin-page lint`: no failures beyond the ones `dev/edd` already has.
  - `pnpm build:blocks`: compiled.

- [ ] **Step 4: Verify the route and the admin**

  - `wp eval` a `rest_do_request( new WP_REST_Request( 'GET', '/yaycheckout/v1/design' ) )`: expect status 404, code `rest_no_route`.
  - Reload wp-admin → YayCheckout: expect the header nav to have no Design tab, and every other tab to open.
  - Open a layout in the editor: expect the Layout design sidebar to work as in Task 2 Step 4, with no console error.

- [ ] **Step 5: Commit**

  Stage the files above, deletions included. Message: `feat(design): remove the global Design page, route and option model`.

### Task 4: Site-wide custom CSS in Settings → Advanced

**Files:**
- Modify: `includes/Models/Settings.php`, `includes/Renderer/CheckoutBaseStyles.php`
- Modify: `apps/admin/src/lib/schema/settings.schema.ts`, `apps/admin/src/lib/api/settings.api.ts`, `apps/admin/src/pages/settings/components/AdvancedTab.tsx`
- Modify (docs): `docs/architecture.md:62` (the Rendering paragraph: the site CSS, then the layout's)

**Interfaces:**
- Consumes: Task 1's `LayoutDesign::sanitize_custom_css()`, `CUSTOM_CSS_MAX_BYTES` and `DesignStyles::css( LayoutDesign, string $site_css, bool )`.
- Produces:
  - In `Settings::schema()`, `advanced.custom_css` is `[ 'type' => 'css', 'default' => '' ]`.
  - On the wire it is `settings.advanced.custom_css: string`, and in the form `advanced.customCss: string`.

- [ ] **Step 1: Add the `css` rule to `Settings`**

  - `is_valid()` accepts a `css` value when it is a string and `LayoutDesign::sanitize_custom_css( $value )` returns it unchanged.
  - `sanitize()` returns `sanitize_custom_css()`'s own `WP_Error` for an invalid `css` value, instead of `invalid()`.
  - `from_option()` keeps its current behaviour, so an invalid stored value reads as `''`.

- [ ] **Step 2: Print it on the storefront**

  When `$with_custom_css` is true, `CheckoutBaseStyles::enqueue()` passes `( new SettingsRepository() )->get()->advanced['custom_css']` as `$site_css`. Otherwise it passes `''`.

- [ ] **Step 3: Add it to the SPA**

  - Add `custom_css: z.string()` to `settingsWireSchema.advanced` and `customCss: z.string().max( 50000 )` to the form schema, both strict.
  - Map it in `settings.api.ts`.
  - In `AdvancedTab.tsx`, add a `SettingRow`:
    - title: "Custom CSS";
    - description: "Printed on every checkout, before each layout's own custom CSS. Not shown in the editor.";
    - field: a monospace `Textarea` with `rows={ 8 }`, `maxLength={ 50000 }` and placeholder `.yaycheckout-checkout { }`.

  The existing Save footer saves it. A 400 shows its message through the existing error path.

- [ ] **Step 4: Verify the route**

  Run `wp eval` with `rest_do_request( POST /yaycheckout/v1/settings )`, merging into the current settings. Expect:
  - `advanced.custom_css` = 50001 × `a`: 400 "Custom CSS must be under 50 KB.", and the stored value unchanged;
  - `'</style>'`: 400 "Custom CSS contains disallowed markup.";
  - `.x{color:red}`: 200, and `GET /settings` returns it;
  - a fresh install with no key returns `''`.

- [ ] **Step 5: Verify the order on the storefront and the editor**

  1. Save `.yaycheckout-checkout{outline:1px solid red}` in Settings → Advanced. Expect it to persist after a reload.
  2. Give the active layout a custom CSS of `.yaycheckout-checkout{outline-offset:2px}`.
  3. Load the checkout. Expect the inline style `yaycheckout/style/checkout-inline-css` to hold the site rule, then the layout rule, after the tokens.
  4. Set the layout CSS to a value containing `</style` through `wp post meta update`. Expect the site rule still prints and the layout rule is dropped.
  5. Open the editor. Expect the canvas inline style to hold neither rule.
  6. Restore both values.

- [ ] **Step 6: Run the checks**

  - `composer lint -- includes/Models/Settings.php includes/Renderer/CheckoutBaseStyles.php`: clean.
  - `composer test`: OK.
  - `tsc --noEmit`: exit 0.
  - Admin lint: no new failures.

- [ ] **Step 7: Commit**

  Stage the files above. Message: `feat(settings): add site-wide custom CSS in Settings → Advanced`.

### Task 5: Record the decision and verify end to end

**Files:**
- Create: `docs/decisions/2026-10-10-layout-owns-its-design.md`
- Modify:
  - `docs/plans/2026-10-09-layout-design-override.md`: Status line only, set to `superseded by [docs/specs/2026-10-10-remove-global-design-design.md](../specs/2026-10-10-remove-global-design-design.md)`.
  - `assets/css/checkout-base.css:5`: the comment says the values come from the layout's design tokens (DesignStyles).
  - this plan: Status `done YYYY-MM-DD`.

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: none.

- [ ] **Step 1: Write the decision record**

  Follow `docs/decisions/2026-10-05-decision-record-practice.md`. The title is "A layout owns its whole design; there is no global design".
  - Status: Accepted 2026-10-10.
  - Context: today's two-layer design, the plugin not yet shipped, and the options turned down (approaches B and C, `null` sections as Theme default, a migration, Pro-only or General-tab site CSS).
  - Decision: the rules of the spec's Approach.
  - Consequences: one model to change; an old or partial layout shows the theme; site CSS isn't exported with a layout; `design-css.ts` still mirrors `DesignStyles`.

- [ ] **Step 2: Update the comment and the old plan's Status**

- [ ] **Step 3: Run the end-to-end check (spec criterion 11)**

  1. Open wp-admin. There is no Design tab.
  2. Create a layout from Classic two-column and open it in the editor. Expect a `#f0f0f1` page and a `#3858e9` primary.
  3. Clear Primary in the sidebar and save. Expect the canvas and the storefront Place order button to use the theme's colour, and no `--yaycheckout-color-primary` to print on either.
  4. Save `.yaycheckout-checkout{outline:1px solid red}` in Settings → Advanced. Load the checkout with that layout active. Expect the rule before the layout's custom CSS.
  5. Place an order with Cash on delivery. Expect the order received page.
  6. Restore the site: delete the test layout, leave layout 61 the only active layout, and clear the site CSS.

- [ ] **Step 4: Run the checks**

  - `composer lint`: clean.
  - `composer test`: OK.
  - `tsc --noEmit`: exit 0.
  - Admin lint: no new failures.
  - `pnpm build:blocks`: compiled.

- [ ] **Step 5: Set this plan's Status to `done YYYY-MM-DD`, and commit**

  Stage the files above. Message: `docs(decisions): record that a layout owns its whole design`.
