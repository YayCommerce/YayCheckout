# Layout Design Override Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract.

**Status:** active
**Goal:** Each layout can override the global Design per section (Colours, Typography, Inputs) and add its own custom CSS. The merchant sets this in a plugin sidebar in the layout editor, and the canvas restyles live.
**Spec:** none, planned from the request
**Architecture:** The override is one post meta on the layout, `_yaycheckout_design`, registered with `show_in_rest`. The editor's existing Save (core `savePost()`) writes it with the markup, so there is no new route or writer. Its REST schema is derived from `Design::schema()`, so core rejects an invalid token with a 400 and no second schema exists. The storefront and the canvas print the global tokens with each overridden section swapped in, then the global custom CSS, then the layout's. In the editor, the sidebar recomputes that CSS and replaces the text of the canvas's existing inline design `<style>`. We chose this over printing a second, override-only stylesheet, which would need `initial` resets to undo a global section the override replaces.
**Touches:** `includes/Models/LayoutDesign.php` (new), `includes/Models/Design.php`, `includes/Models/Layout.php`, `includes/PostTypes/LayoutPostType.php`, `includes/Renderer/DesignStyles.php`, `includes/Renderer/CheckoutBaseStyles.php` (shared: `CheckoutPage`, `LayoutEditorCanvas`), `includes/Renderer/CheckoutPage.php`, `includes/Engine/Admin/LayoutEditorCanvas.php`, `includes/Blocks/BlockRegistry.php`, `includes/Repositories/LayoutRepository.php`, `includes/Pro/Controllers/LayoutExportRestController.php`, `includes/Pro/Controllers/LayoutImportRestController.php`, `includes/Pro/Services/LayoutImportReview.php`, `packages/blocks/src/shared/editor-data.ts` (shared: every reader of `editorData`, and the SPA via `@blocks-shared`), `packages/blocks/src/shared/design-css.ts` (new), `packages/blocks/src/checkout/layout-design.tsx` (new), `packages/blocks/src/checkout/layout-design.css` (new), `packages/blocks/src/checkout/index.ts`, `packages/blocks/src/checkout/import-export.tsx`, `docs/architecture.md`, `docs/conventions.md`
**Tests:** none. The user declined the proposed import-review test. Each task has an exact manual check instead.

## Requirements

- **Goal:** In the layout editor, a **Layout design** sidebar lets the merchant choose for each of Colours, Typography and Inputs whether the layout follows the global Design or uses its own values, and give the page background a gradient. The merchant can also add custom CSS for this layout. The canvas shows the result before Save, and the storefront and preview links show it after Save.
- **Out of scope:**
  - The SPA's Design page and its Preview. They keep editing the global design only and don't list which layouts override it.
  - Revisions of the override. Meta isn't revisioned, so restoring a revision restores markup only.
  - Showing the layout's custom CSS in the canvas. Like the global custom CSS, it prints on the storefront only.
  - Loading fonts. A picked font is named, not enqueued, as today.
- **Acceptance criteria:**
  1. **Modes:** each section offers exactly two choices: **Global design** (stored `null`, follows later Design-page changes) and **Custom** (stored with `enabled: true` and the section's tokens). There is no Theme default choice. Switching to Custom starts from the global section's values when that section is on, and from `Design::defaults()` when it's off.
  2. **Controls:** Custom shows the same tokens and bounds as the Design page. Colours: primary, text, background and border, as the block editor's colour items (`ColorGradientSettingsDropdown`, class `block-editor-color-gradient-item`) with the theme's full palettes. A theme swatch is stored as its preset's custom property (`var(--wp--preset--color--<slug>)`, or `--gradient--` for a gradient) and shows as selected; a custom pick is stored as written; clicking the selected swatch again unsets the colour (`''`), which prints nothing, so the theme's colour shows. Background alone also has a Gradient tab: a layout's background may be a gradient (`LayoutDesign::schema()` makes it a `fill`), which fills the page as `background-image` while inputs and rows keep their solid fallback. The global design stays hex-only. Typography: font family from `Design::FONT_FAMILIES` and base size 10–24 px. Inputs: style boxed or underline, size small, medium or large, and radius 0–24 px, with radius hidden while the style is underline.
  3. **Custom CSS:** a textarea for this layout. Its CSS prints on the storefront after the global custom CSS. When it is over 50000 bytes, or contains `</style`, `<script` or a NUL byte, the sidebar shows the server's message and Save stays locked until it's fixed.
  4. **Save:** the header's Save, and Activate, which saves first, store the override with the markup. Editing only the override marks the layout dirty.
  5. **Storefront:** the checkout and a preview link print the global tokens with the overridden sections replaced. A gradient background prints `--yaycheckout-background-gradient` and the page rule's `background-image`, and no `--yaycheckout-color-background`. A layout with no override prints exactly what it prints today.
  6. **Live canvas:** changing any token or mode restyles the canvas at once, without Save, including after switching the editor's Desktop, Tablet or Mobile preview.
  7. **Copies:** Duplicate copies the override. Pro's export file carries it, and both import paths (the SPA's Tools import and the editor's Import modal) restore it. A file without one, or with a malformed one, imports with every section on Global design and no custom CSS.
  8. **End to end:** turn the global Colours on with primary `#3858e9`. In layout A, set Colours to Custom with primary `#e11d48` and a gradient background, add `.yaycheckout-page{outline:1px solid red}` as custom CSS, Save, and activate. The storefront checkout's `:root` has `--yaycheckout-color-primary: #e11d48` and `--yaycheckout-background-gradient`, the page has that gradient as `background-image`, and the outline shows. Duplicate A, then export and import the duplicate (Pro): both copies open with the same three settings in the sidebar.
- **Decided with the user:**
  - **Granularity:** per section, not one switch for the whole design.
  - **Plan:** Free and Pro. Export and import stay Pro-only because their routes are.
  - **Also in scope:** live canvas preview, Duplicate copies the override, export and import carry it, and per-layout custom CSS.
  - **Tests:** none.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types (CLAUDE.md rule 4; see `.claude/rules/php-standards.md`).
- Free names Pro only in `boot_pro()`. Nothing outside `includes/Pro/` may reference `YayCheckout\Pro` (`composer lint` runs `scripts/check-pro-boundary.sh`).
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/` and run `pnpm build:blocks`.
- Storage keys are class constants: `Layout::META_DESIGN`, never the string at a call site. The editor gets the key from `editorData.design.metaKey`.
- A TypeScript copy of PHP carries a `Mirrors …` comment naming its PHP owner, and both sides change in the same commit (docs/conventions.md).
- Data printed for scripts uses camelCase keys. PHP payloads inside it (tokens, schema) stay snake_case.
- Sidebar UI uses `@wordpress/components` only, with no inline styles (`.claude/rules/blocks.md`). Import `PluginSidebar` and `PluginSidebarMoreMenuItem` from `@wordpress/editor`, as `import-export.tsx` does for `PluginDocumentSettingPanel`.
- Commits follow Conventional Commits and end with the `Co-Authored-By` trailer from CLAUDE.md. Branch from `dev/edd` as `<name>/feat/layout-design-override`.
- `composer lint` and `pnpm build:blocks` must pass. Nothing type-checks the blocks' TypeScript, so build them and open a layout in the editor.

## Review focus

- **A preset or gradient the schema refuses:** a theme swatch is stored as `var(--wp--preset--…)`, which `Design::PRESET_COLOR_PATTERN`/`PRESET_GRADIENT_PATTERN` accept, and a crafted save with `;}` in a gradient or variable, or any other `var()`, is refused with a 400.
- **The control's paired clear:** a pick calls `onColorChange( value, slug )` then `onGradientChange()` (or the reverse); only a call for the kind the token holds unsets it, so a pick never clears itself.
- **Empty design in the canvas:** with the global design and the override both printing nothing, WordPress prints no inline style; the sidebar creates it, so the live preview still works.
- **Global section changed after a layout chose Global design:** the layout follows the new global value. A `null` section must never be stored as a copied snapshot. Task 2, Step 4.
- **Crafted wp/v2 save with an out-of-range token** (`radius: 99`): core answers 400 and the stored meta is unchanged. Task 1, Step 4.
- **Layout custom CSS that `Design::sanitize_custom_css()` rejects, stored without the editor** (direct `update_post_meta`): the storefront still prints the global custom CSS and skips only the layout's. Task 1, Step 5.
- **Canvas iframe replaced** (device preview switch, or code editor and back): unsaved override values stay visible. Task 3, Step 3.
- **Old export file without `design`:** it imports, with all sections on Global design. Task 4, Step 4.

---

### Task 1: Store the override and print it on the storefront

**Files:**
- Create: `includes/Models/LayoutDesign.php`
- Modify: `includes/Models/Layout.php`, `includes/PostTypes/LayoutPostType.php`, `includes/Renderer/DesignStyles.php`, `includes/Renderer/CheckoutBaseStyles.php`, `includes/Renderer/CheckoutPage.php`, `includes/Engine/Admin/LayoutEditorCanvas.php`, `docs/architecture.md`

**Interfaces:**
- Produces:
  - `Layout::META_DESIGN = '_yaycheckout_design'`.
  - `Layout::$design` (`LayoutDesign`), always set by `Layout::from_post()` from that meta.
  - `final class LayoutDesign` in `YayCheckout\Models`:
    - `public array $sections`: section => full token array, or `null` for Global design.
    - `public string $custom_css = ''`.
    - `public static function none(): self`: every section `null`, CSS `''`.
    - `public static function from_meta( $value ): self`: projects like `Design::from_option()`. A section that isn't an array is `null`. An array section has each token taken when `Design`'s rule accepts it and the schema default otherwise. `custom_css` is kept when it's a string, else `''`. Anything but an array is `none()`.
    - `public function to_meta(): array`: `{ colours: array|null, typography: array|null, inputs: array|null, custom_css: string }`, every key always present. This is also the wire and export shape.
    - `public function tokens_over( array $tokens ): array`: `$tokens` with each non-null section replaced.
    - `public static function rest_schema(): array`: a JSON Schema object built from `Design::schema()`. Each section is `type: ['object','null']` with `additionalProperties: false` and every token required. The type maps are `bool` → `boolean`, `int` → `integer` with `minimum`/`maximum`, `enum` → `string` + `enum`, and `hex_color` → `string` + `pattern: Design::HEX_COLOR_PATTERN`. `custom_css` is `string`. Top level: `additionalProperties: false`, all four keys required.
  - `Design::project_section( string $section, $values ): array`, public, which `Design::project()` also uses: each token from `$values` when valid, else its default. `from_meta()` calls it for each array section. `Design::HEX_COLOR_PATTERN = '^#[0-9A-Fa-f]{6}$'`, used by `Design::is_valid()` (still private) and `rest_schema()`. No other `Design` change.
  - `DesignStyles::css( Design $design, LayoutDesign $layout, bool $with_custom_css ): string`. Vars and the page rule come from `$layout->tokens_over( $design->tokens )`. Then the global custom CSS, then the layout's, are each checked with `Design::sanitize_custom_css()` and skipped on `WP_Error`.
  - `CheckoutBaseStyles::enqueue( bool $with_custom_css, LayoutDesign $layout_design ): void`.

- [ ] **Step 1: Add `LayoutDesign` and `Layout::META_DESIGN` / `Layout::$design`**

`Layout::from_post()` sets `$layout->design = LayoutDesign::from_meta( self::meta_value( $meta, self::META_DESIGN, null ) )`. `$design` isn't added to `to_array()`, so the SPA's strict layout wire schema is unchanged.

- [ ] **Step 2: Register the meta in `LayoutPostType::register()`**

Call `register_post_meta( Layout::POST_TYPE, Layout::META_DESIGN, [...] )` after `register_post_type()`, with:
- `type: 'object'`, `single: true`, `default: LayoutDesign::none()->to_meta()`
- `show_in_rest: [ 'schema' => LayoutDesign::rest_schema() ]`
- `sanitize_callback`: `LayoutDesign::from_meta( $value )->to_meta()`
- `auth_callback`: `current_user_can( LayoutPostType::CAPABILITY )`, which the protected `_` key requires.

- [ ] **Step 3: Thread the override through the styles**

Change `DesignStyles::css()` and `CheckoutBaseStyles::enqueue()` to the signatures above. `CheckoutPage::enqueue_scripts()` passes `$this->layout()->design`. `LayoutEditorCanvas::canvas_base_styles()` passes the edited layout's design, read with `( new LayoutRepository() )->find( (int) get_the_ID() )`, or `LayoutDesign::none()` when that returns `null`. Update the `DesignStyles` class docblock: "the Design page's tokens, with the layout's overridden sections swapped in".

- [ ] **Step 4: Verify the REST schema**

Run `wp post meta update <id> _yaycheckout_design '{"colours":{"enabled":true,"primary":"#e11d48","text":"#171719","background":"#ffffff","border":"#e4e4e7"},"typography":null,"inputs":{"enabled":false,"style":"boxed","size":"medium","radius":10},"custom_css":".yaycheckout-page{outline:1px solid red}"}' --format=json` in the Local site shell, on the active layout.
Then, logged in as admin in the browser console on any wp-admin page, run `wp.apiFetch({path:'/wp/v2/yay_checkout_layout/<id>?context=edit'}).then(r=>r.meta._yaycheckout_design)`.
Expected: the object above.
Then run `wp.apiFetch({path:'/wp/v2/yay_checkout_layout/<id>',method:'POST',data:{meta:{_yaycheckout_design:{colours:null,typography:null,inputs:{enabled:true,style:'boxed',size:'medium',radius:99},custom_css:''}}}})`.
Expected: rejects with code `rest_invalid_param`, and the first GET still returns the object above.

- [ ] **Step 5: Verify the storefront**

Turn the global Colours and Inputs on in Design and save. View source on the storefront checkout.
Expected: the `yaycheckout/style/checkout-inline-css` style has `--yaycheckout-color-primary: #e11d48;`, no `--yaycheckout-input-` line, then the global custom CSS, then `.yaycheckout-page{outline:1px solid red}`.
Then run `wp post meta update <id> _yaycheckout_design '{"colours":null,"typography":null,"inputs":null,"custom_css":"</style><b>x"}' --format=json`. The sanitize callback keeps the string, because only the printer checks it. Reload.
Expected: the global primary is back, the input properties are back, the global custom CSS still prints, and `</style` appears nowhere in the page.
Then run `wp post meta delete <id> _yaycheckout_design` and reload.
Expected: the inline style is byte-identical to the one on `dev/edd` for the same global design.

- [ ] **Step 6: Lint**

Run `composer lint -- includes/Models/LayoutDesign.php includes/Models/Layout.php includes/Models/Design.php includes/PostTypes/LayoutPostType.php includes/Renderer/DesignStyles.php includes/Renderer/CheckoutBaseStyles.php includes/Renderer/CheckoutPage.php includes/Engine/Admin/LayoutEditorCanvas.php`
Expected: no errors.

- [ ] **Step 7: Update `docs/architecture.md`**

Make three edits:
- **Writers:** core's wp/v2 also writes `Layout::META_DESIGN` from the editor.
- **Storefront → Rendering:** `DesignStyles` prints the global tokens with the layout's overridden sections (`LayoutDesign::tokens_over()`), then the global custom CSS, then the layout's, each checked on print.
- **Layers:** `Models/` now includes `LayoutDesign`.

- [ ] **Step 8: Commit**

Stage: the files listed under **Files** for this task.
Message: `feat(design): store a per-layout design override and print it on the storefront`

### Task 2: Layout design sidebar

**Files:**
- Create: `packages/blocks/src/checkout/layout-design.tsx`, `packages/blocks/src/checkout/layout-design.css`
- Modify: `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/editor-data.ts`, `packages/blocks/src/checkout/index.ts`, `docs/architecture.md`

**Interfaces:**
- Consumes: `Layout::META_DESIGN`, `LayoutDesign::to_meta()` shape, `Design::schema()`, `Design::FONT_FAMILIES`, `Design::CUSTOM_CSS_MAX_BYTES` (Task 1).
- Produces:
  - `window.yayCheckoutBlocks.design`, added by `BlockRegistry::editor_design_data()` on `enqueue_block_editor_assets` (so it isn't built on every request): `{ metaKey: Layout::META_DESIGN, styleId: ScriptName::STYLE_CHECKOUT . '-inline-css', tokens: ( new DesignRepository() )->get()->tokens, schema: Design::schema(), fontFamilies: Design::FONT_FAMILIES, customCssMaxBytes: Design::CUSTOM_CSS_MAX_BYTES }`.
  - `editorData.design` with type `EditorDesign` exported from `editor-data.ts`. The fallback when the global is missing is `null`, and the sidebar registers nothing then.
  - `type LayoutDesignMeta = { colours: Record<string, unknown> | null; typography: … | null; inputs: … | null; custom_css: string }`, exported from `layout-design.tsx`.
  - `useLayoutDesign( design ): [ LayoutDesignMeta, ( next: LayoutDesignMeta ) => void ]`, local to `layout-design.tsx`. It reads `getEditedPostAttribute( 'meta' )[ metaKey ]` and writes with `editPost( { meta: { [ metaKey ]: next } } )`, always sending all four keys.
  - `effectiveTokens( globalTokens, override: LayoutDesignMeta )`, local to `layout-design.tsx`. It returns `globalTokens` with the non-null sections replaced, the same rule as `LayoutDesign::tokens_over()`.
  - Plugin `yaycheckout-layout-design`: `PluginSidebar` name `yaycheckout-layout-design`, title "Layout design", icon `brush`, plus a `PluginSidebarMoreMenuItem`. It renders only when `getCurrentPostType() === editorData.postType`.
  - Save lock id `yaycheckout-layout-design-css`.

- [ ] **Step 1: Print the editor data**

Add `BlockRegistry::editor_design_data()` and extend `EditorData` in `editor-data.ts`.

- [ ] **Step 2: Build the sidebar in `layout-design.tsx`**

Make one `PanelBody` per section, titled Colours, Typography and Inputs. Each panel has a `ToggleGroupControl` with the values `global` and `custom`, labelled "Global design" and "Custom". A section is `null` → `global`, anything else → `custom`.
- Switching to `custom` stores the section seeded per acceptance criterion 1 (from `editorData.design.tokens` when that section is on there, else from each rule's `default` in `schema`), with `enabled: true`.
- `global` stores `null`.
- Custom controls:
  - Colours: `__experimentalColorGradientSettingsDropdown` in a `ToolsPanel` styled as core's `color-block-support-panel`, no alpha, not clearable; palettes from `__experimentalUseMultipleOriginColorsAndGradients()`; `colorSlug`/`gradientSlug` mark a stored preset as selected; a `fill` token (background) also gets `gradientValue`/`onGradientChange`.
  - Typography: `SelectControl` over `fontFamilies`, with `''` labelled "Theme default", and `RangeControl` for `base_size`.
  - Inputs: `ToggleGroupControl` for style, `SelectControl` for size, and `RangeControl` for radius, hidden while style is `underline`.
  - Bounds and enums come from `schema`, never literals.

A last `PanelBody`, "Custom CSS", holds a `TextareaControl` bound to `custom_css`, with the help text "Printed on the checkout after the global custom CSS. Not shown in the editor."

- [ ] **Step 3: Lock Save on CSS the server would skip**

Add `function customCssError( css: string, maxBytes: number ): string | null`, commented `Mirrors Design::sanitize_custom_css() in PHP.` It returns the same two messages as PHP: over `maxBytes` UTF-8 bytes (`new TextEncoder().encode( css ).length`), or a match for `/<\/style|<script/i` or a NUL byte. While it returns a message, `lockPostSaving( 'yaycheckout-layout-design-css' )` and show the message as the textarea's error. Otherwise unlock.

- [ ] **Step 4: Verify in the editor**

Run `pnpm build:blocks` and open layout A in the SPA's editor modal. Open **Layout design** from the header's plugin icon.
- Set Colours to Custom, primary to `#e11d48` and a gradient background, and leave Typography on Global design.
  Expected: the header's Save turns active.
  Save, close and reopen the modal.
  Expected: the sidebar shows the same three modes and `#e11d48`.
- Change the global Typography font on the Design page, then reload the storefront.
  Expected: layout A uses the new font, because Typography follows the global design.
- Type `<script>` in Custom CSS.
  Expected: the message "Custom CSS contains disallowed markup." shows and Save is disabled. Delete it, and Save is enabled again.

- [ ] **Step 5: Verify other editors are untouched**

Open a normal post in the block editor.
Expected: no "Layout design" item in the plugin menu, and no console errors.

- [ ] **Step 6: Update `docs/architecture.md`**

In **Blocks and the embedded editor**, add one sentence: `checkout/layout-design.tsx` puts the Layout design sidebar in the layout editor and edits `Layout::META_DESIGN` through the post's meta, so the header's Save stores it.

- [ ] **Step 7: Commit**

Stage: the files listed under **Files** for this task.
Message: `feat(blocks): add the Layout design sidebar to the layout editor`

### Task 3: Live canvas preview

**Files:**
- Create: `packages/blocks/src/shared/design-css.ts`
- Modify: `packages/blocks/src/checkout/layout-design.tsx`

**Interfaces:**
- Consumes: `useLayoutDesign()`, `effectiveTokens()` (Task 2 Interfaces), `editorData.design` (Task 2 Interfaces).
- Produces:
  - `designCss( tokens, fontFamilies: Record<string, string> ): string` in `design-css.ts`, commented `Mirrors DesignStyles::css() in PHP, without custom CSS.` It holds the copies of `FONT_FALLBACK`, `PAGE_SELECTOR`, `PAGE_PROPERTIES`, `INPUT_HEIGHTS` and the `vars()` mapping, each naming its PHP constant. Font names come from `fontFamilies`, not a copy.
  - The canvas style's id comes from `editorData.design.styleId` (Task 2 Interfaces), so the blocks hold no copy of it.

- [ ] **Step 1: Write `designCss()`**

For the same tokens, its output must equal `DesignStyles::css( $design, LayoutDesign::none(), false )` byte for byte, including tabs and newlines, so a canvas never changes on its first replacement.

- [ ] **Step 2: Apply it to the canvas**

In the plugin's root component, outside `PluginSidebar` (whose children unmount when it's closed), add an effect. Once the override differs from the saved one, it sets the `textContent` of `getElementById( editorData.design.styleId )` to `designCss( effectiveTokens( editorData.design.tokens, override ), editorData.design.fontFamilies )`. It looks in the `iframe[name="editor-canvas"]` document, or in `document` when the editor isn't iframed. It re-applies when that iframe is replaced or reloads, so catch each iframe `load` and watch each canvas `<head>`. When the element isn't found, it does nothing.

- [ ] **Step 3: Verify**

Run `pnpm build:blocks` and open layout A.
- Change the Colours primary in the sidebar.
  Expected: the Place order button in the canvas changes colour at once, without Save.
- Switch the editor's preview to Mobile, then back to Desktop.
  Expected: the unsaved colour is still shown.
- On a layout whose global design and override print nothing, set Colours to Custom with a gradient background.
  Expected: the canvas background shows the gradient at once.
- Save and reload with no sidebar edits, and compare `document.querySelector('iframe[name="editor-canvas"]').contentDocument.getElementById('yaycheckout/style/checkout-inline-css').textContent` before and after one no-op change (change a colour and change it back).
  Expected: identical strings.

- [ ] **Step 4: Verify the SPA still builds**

Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`, because `shared/` is `@blocks-shared`.
Expected: no new errors compared with `dev/edd`.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/shared/design-css.ts`, `packages/blocks/src/checkout/layout-design.tsx`
Message: `feat(blocks): restyle the layout editor canvas live from the design override`

### Task 4: Duplicate, export and import carry the override

**Files:**
- Modify: `includes/Repositories/LayoutRepository.php`, `includes/Pro/Controllers/LayoutExportRestController.php`, `includes/Pro/Services/LayoutImportReview.php`, `includes/Pro/Controllers/LayoutImportRestController.php`, `packages/blocks/src/checkout/import-export.tsx`, `docs/architecture.md`, `docs/conventions.md`

**Interfaces:**
- Consumes: `LayoutDesign::from_meta()`, `to_meta()`, `Layout::$design`, `Layout::META_DESIGN` (Task 1 Interfaces); `editorData.design.metaKey` and `LayoutDesignMeta` (Task 2 Interfaces).
- Produces:
  - `LayoutRepository::insert_draft( string $title, string $content, array $rules, LayoutDesign $design )`. It always adds `Layout::META_DESIGN => $design->to_meta()` to `meta_input`; `create()` passes `LayoutDesign::none()`.
  - `LayoutRepository::import( string $title, string $content, array $rules, LayoutDesign $design ): Layout`.
  - `duplicate()` passes `$source->design`.
  - The export envelope's layout entry gains `'design' => $layout->design->to_meta()`.
  - `LayoutImportReview::from_envelope()` returns `{ title, content, rules, design }`, where `design` is `LayoutDesign::from_meta()` of the file's value, so it never refuses the file.
  - `POST /layouts/import/review` adds `design` (`to_meta()` shape). `POST /layouts/import` stores it.
  - The editor's `ImportModal` `onImport( content: string, design: LayoutDesignMeta )`.

- [ ] **Step 1: Duplicate and the repository**

Make the repository changes above.

- [ ] **Step 2: Export and import, server side**

Make the Pro controller and service changes above.

- [ ] **Step 3: Editor Import applies the design**

In `import-export.tsx`, add `design: LayoutDesignMeta` to `ImportReview`. `onImport` also writes the design with `editPost()` under `editorData.design.metaKey`, so Save stores it with the markup.

- [ ] **Step 4: Verify**

Run `pnpm build:blocks`, and use the Pro dev build, where `./scripts/run.sh dev` sets the plan to `pro`.
- Duplicate layout A in the SPA and open the copy.
  Expected: the sidebar shows A's three modes and CSS.
- Export the copy from the editor's Import / Export panel.
  Expected: the file's `layouts[0].design` equals the GET in Task 1 Step 4.
- Import that file in SPA Tools.
  Expected: the new draft's sidebar matches.
- Delete the `design` key from the file and import it again.
  Expected: it imports, and every section shows Global design with empty CSS.
- Set `design.colours` to `"x"` and import.
  Expected: it imports, with Colours on Global design.
- In a fresh layout's editor, use Import with the original file.
  Expected: the canvas restyles before Save, and after Save the storefront preview link shows the override.

- [ ] **Step 5: Lint**

Run `composer lint`.
Expected: passes, including the Pro-boundary check.

- [ ] **Step 6: Update `docs/architecture.md`**

Make three edits:
- **Layouts:** `duplicate()` copies `Layout::META_DESIGN` along with the rules.
- **Export:** the envelope comment lists title, markup, rules and design.
- **Import:** a missing or malformed design imports as Global design.

- [ ] **Step 7: Commit**

Stage: the files listed under **Files** for this task.
Message: `feat(layouts): copy the design override on duplicate, export and import`

- [ ] **Step 8: Final check (acceptance criterion 8)**

On a fresh Pro dev site, run acceptance criterion 8 exactly as written.
Expected: every result it states.
