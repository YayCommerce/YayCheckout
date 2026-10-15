# Per-Layout Design Override Design

**Status:** superseded by [docs/specs/2026-10-10-remove-global-design-design.md](2026-10-10-remove-global-design-design.md)
**Related:** YC-04 Design mock (override layer states); docs/decisions/2026-10-05-layout-is-one-post.md; branch `alan/feat/design-page-tokens` (removes the spacing and buttons sections and design presets)

## Goal

A Pro merchant can give one layout its own colours, typography, inputs or custom CSS from a **Design** sidebar in that layout's editor, opened from its own toolbar button, and see the change on the canvas as they make it. Every group they leave on **Global** keeps following the Design page, including later edits there.

## Context

- Global design lives in the `yaycheckout_design` option (`Models\Design`, `Repositories\DesignRepository`). `Design::schema()` defines the token sections. Once `alan/feat/design-page-tokens` merges, these are `colours`, `typography` and `inputs`, each with an `enabled` switch.
- `Renderer\CheckoutBaseStyles::enqueue( bool $with_custom_css )` is the one place that prints the tokens. It enqueues `assets/css/checkout-base.css`, then `DesignStyles::css()` as inline style on `ScriptName::STYLE_CHECKOUT`. Two callers use it: the storefront (`CheckoutPage::enqueue_scripts()`, with custom CSS) and the layout editor canvas (`Engine\Admin\LayoutEditorCanvas`, without custom CSS). `checkout-base.css` and the blocks' CSS read `--yaycheckout-color-*`, `--yaycheckout-font-*` and `--yaycheckout-input-*`.
- `CheckoutPage::layout()` resolves the served layout once per request, preview links included (`PreviewRequest`).
- `Renderer\CheckoutColour` prints the global primary colour on `enqueue_block_assets` for six blocks, in the editor and on the storefront.
- Layout meta precedent: `Layout::META_RULES` (`_yaycheckout_rules`) is read into `Layout::$rules` in Free, written by `LayoutRepository`, served by a Pro route, carried by `duplicate()` (Free) and by export and import (Pro, `LayoutExportRestController`, `Pro\Services\LayoutImportReview`). Import ignores unknown keys in a layout entry.
- The editor is core's `post.php`, embedded in an SPA modal with `?yc-embed=1`. `packages/blocks/src/checkout/import-export.tsx` is a `PluginDocumentSettingPanel` in the Layout tab and shows a locked Pro teaser in Free from `editorData.isPro`. `BlockRegistry` prints `window.yayCheckoutBlocks` (`isPro`, `postType`, `upgradeUrl`). The SPA opens the editor from `#/layouts?edit=<id>` and closes it when the route changes. The plugin supports WordPress 6.5 and up, where `Navigator` is still `__experimentalNavigator*`.

## Scope

**In:**
- A `_yaycheckout_design` meta on each layout holding a **per-group** override layer: whole token sections plus optional custom CSS.
- The effective design (global with the layer on top) on the storefront, in preview links, and on the layout editor canvas, including `CheckoutColour`'s primary.
- A **Design** sidebar opened by its own toolbar button (next to Settings), laid out like core's Styles sidebar: a list of groups (one per token section, then Custom CSS), each opening a screen with a Global / Custom switch and its controls, with live canvas update. Saved with the layout's Save.
- A locked Pro teaser of the sidebar in Free.
- "Edit global design ↗" in the sidebar, which closes the editor and opens `#/design`, after asking when the editor has unsaved changes.
- Duplicate, export and import carry the layer.

**Out:**
- Per-token overrides inside a group, per-row "Global / Overridden" labels and per-row ↺ (YC-04 state 7). Override is per group.
- A layout picker or override UI on the Design page, and a "Design" item in the Layouts list. The Design page stays global only.
- Templates shipping their own layer (catalogue has no markup yet: `TODO(template-source)`).
- Showing on the Design page which layouts override which groups.
- Custom CSS on the editor canvas. The editor prints no custom CSS today, global or layout.

## Acceptance criteria

1. Given Pro and layout L with no meta, when L renders on the storefront, then the inline `:root` block and custom CSS are byte-identical to the global output today.
2. Given global `colours` = `{ enabled: true, primary: "#3858e9", … }` and L's layer `colours` = `{ enabled: true, primary: "#171719", text: "#171719", background: "#ffffff", border: "#e4e4e7" }`, when L renders, then `--yaycheckout-color-primary: #171719`, and typography and input variables equal the global ones.
3. Given (2), when the merchant changes the global `typography.base_size` to 18 and saves the Design page, then L renders `--yaycheckout-font-size-base: 18px` and still `--yaycheckout-color-primary: #171719`.
4. Given global custom CSS `G` and L's layer `custom_css` = `C`, when L renders, then the inline style holds the variables, then `G`, then `C`, each on its own line.
5. Given Pro with two active layouts L1 (layer) and L2 (no meta), when shoppers are served each, then each page prints its own effective design. A preview link of a draft with a saved layer shows that layer.
6. Given the Free build with L carrying a stored layer, when L renders, then the output equals the global output, `_yaycheckout_design` is not in the `wp/v2` REST response, and the editor's Design button opens the sidebar locked with a Pro tag and an Upgrade button.
7. Given the editor for L, when the merchant clicks the toolbar's Design button, then the Design sidebar opens on the group list, each row showing Global or Custom. When they open **Inputs** and switch it to Custom, then the controls appear seeded with the current global input values and the canvas does not change. When they then pick Underline and radius 0, the canvas fields change at once, before Save.
8. When the merchant presses Save, then the meta holds `{ "tokens": { "inputs": { … } } }` with the four input keys. Reopening the editor shows Inputs on Custom with those values.
9. When the merchant switches a Custom group back to Global, then a confirmation lists each value that changes and its global value (for example "Radius 0px → 10px"). On confirm, after Save, that section is absent from the meta. When no section and no custom CSS is left, the meta is deleted.
10. When a save sends a section with a missing key or an invalid value (`colours.primary: "red"`), or custom CSS containing `</style`, then `wp/v2` answers 400 with the message from `Design` naming the token path or the CSS rule, and nothing is stored. An unknown section or key is dropped, as `Design::sanitize()` drops it for global, and the rest saves.
11. When L is duplicated, then the copy has the same `_yaycheckout_design`. When L is exported, the file's layout entry carries `"design": <layer>`. Importing that file restores it. Importing a file without `design` creates a draft with no meta. A layer with an invalid section imports with that section dropped and the rest kept.
12. When the merchant clicks "Edit global design ↗" with unsaved editor changes, then a confirm dialog asks to discard them. On confirm (or with no changes), the editor closes and the SPA shows `#/design`.
13. End-to-end verification: on yaycheckout.test (Pro), open a layout's editor and click the toolbar's Design button, set Colours to Custom with primary `#171719`, set Inputs to Custom with Underline and radius 0, and Save. Open the layout's preview link: the Place order button is `#171719` and fields are underlined with square corners. On the Design page, change global base size to 18 and save. Reload the preview: text is 18px and the custom colours and inputs remain. Back in the editor, set Colours to Global and Save. The preview button returns to the global primary.

## Constraints

- PHP 7.4 (hard rule 4). Pro attaches only through hooks added in `Pro\ProServiceProvider` (hard rule 2), and Free never applies a layer (hard rule 3).
- Token rules come from `Design::schema()` only, with no second list. A new section added later becomes an overridable group without changes here.
- Wire and meta keys are snake_case. The meta value is the same shape everywhere: storage, `wp/v2` meta, export file.
- `Design::CUSTOM_CSS_MAX_BYTES` (50000) applies to the layer's CSS on its own.
- TypeScript copies of PHP rules carry a `Mirrors …` comment naming the owner (docs/conventions.md). The editor's variable builder mirrors `DesignStyles::vars()` and `DesignStyles::INPUT_HEIGHTS`.
- Tests are proposed in yayplan and written only after the user accepts them (CLAUDE.md § Tests).

## Approach

**Chosen:** A per-group sparse layer in layout meta, written through core's `wp/v2` save from an editor sidebar, merged by a Pro filter.

**Architecture:** The layer stores only the sections and CSS the merchant switched to Custom. Every reader asks `DesignRepository::for_layout()` for the effective design, which applies the filter `yaycheckout_design_for_layout`. Only Pro hooks that filter, so Free always gets the global design. The editor sidebar edits the layer as the post's meta, so it is saved, dirtied and undone together with the layout. A live `<style>` in the canvas shows unsaved values.

**Components:**
- `Models\Layout`: `META_DESIGN = '_yaycheckout_design'`, `public array $design` read with `Design::project_layer()`.
- `Models\Design`:
  - `project_layer( $raw ): array` (read): keeps a section only when every key of it is valid, drops the rest silently, and keeps `custom_css` only when it passes `sanitize_custom_css()` and is non-empty.
  - `validate_layer( array $input )` (write): returns the clean layer or the first `WP_Error`.
  - `with_layer( array $layer ): Design`: returns a copy with the layer's sections replacing global sections whole, and custom CSS set to global CSS plus `"\n"` plus layer CSS.
- `Repositories\DesignRepository::for_layout( ?Layout $layout ): Design`: `get()`, then the filter when `$layout` is not null.
- `Renderer\CheckoutBaseStyles::enqueue( bool $with_custom_css, ?Layout $layout )`: prints `for_layout( $layout )`. `CheckoutPage` passes `$this->layout()`. `LayoutEditorCanvas` passes the edited layout.
- `Renderer\CheckoutColour`: primary from `for_layout()` with `CheckoutPage::layout()` on the storefront, and with the edited layout on the layout editor screen.
- `LayoutRepository::duplicate()` and `import()` pass `$design` to `insert_draft()`, which writes the meta when non-empty.
- `Pro\ProServiceProvider`:
  - hooks `yaycheckout_design_for_layout` to `with_layer`;
  - calls `register_post_meta( Layout::POST_TYPE, Layout::META_DESIGN, … )` on `init`, with single object, `show_in_rest` schema `{ type: object, additionalProperties: true }`, `sanitize_callback` set to `project_layer`, and `auth_callback` set to `LayoutPostType::CAPABILITY`;
  - hooks `rest_pre_insert_yay_checkout_layout` to run `validate_layer` on `meta._yaycheckout_design` and answer 400 on error;
  - adds `globalDesign` (the global tokens in wire shape) to `window.yayCheckoutBlocks` through a filter on the editor data.
- `LayoutExportRestController` adds `design`. `LayoutImportReview::layout()` passes `design` through, and import runs `project_layer`.
- `packages/blocks/src/checkout/layout-design.tsx`, a new `registerPlugin`:
  - a `PluginSidebar` named `yaycheckout-layout-design`, title "Design", icon `styles` from `@wordpress/icons`, imported from the same package as `import-export.tsx`'s panel and working on WordPress 6.5. Core pins it to the toolbar;
  - a root screen listing one row per section from `globalDesign`, plus Custom CSS, and a detail screen per row, switched by local state with a Back button (no `__experimentalNavigator*`);
  - edits the meta through `editPost( { meta } )`;
  - injects `<style id="yaycheckout-layout-design-live">` into the canvas document with the effective variables;
  - locked teaser when `! editorData.isPro`.

**Data and storage:**
- Meta key `_yaycheckout_design` on `yay_checkout_layout` posts. Its value is `{ tokens?: { <section>: { <every key of that section> } }, custom_css?: string }`.
- Absent means fully global. An empty layer is deleted, never stored as `{}`.
- No migration: existing layouts have no meta.
- Uninstall deletes layouts and their meta, as today.
- Downgrade to Free leaves the meta in place, unused. Upgrade applies it again.

**Integration points:**
- New filter `yaycheckout_design_for_layout( Design $design, Layout $layout ): Design`.
- New editor-data key `globalDesign`.
- Core `wp/v2/yay_checkout_layout` meta field `_yaycheckout_design` (Pro only).
- Export file layout entry gains optional `design`, keeping `Layout::EXPORT_FORMAT` (`yaycheckout-layout`). Older plugins ignore the key.
- `CheckoutBaseStyles::enqueue()` gains a parameter, and both callers change.
- Nothing in the SPA changes. The SPA wire for layouts is unchanged, and `Layout::to_array()` does not emit `design`.

**UI:**
- Embedded core editor. A toolbar button "Design" (icon `styles`) sits next to Settings and opens the "Design" sidebar in place of the Settings sidebar, as core's Styles does. Built with `@wordpress/components`.
- **Root screen**, modelled on core's Styles sidebar:
  - a preview card: "Aa" in the effective font, with the effective primary and text colours as two dots;
  - one navigation row per group (Colours, Typography, Inputs), each with its icon and, on the right, "Global" or "Custom" and a chevron;
  - a divider, then the Custom CSS row, showing "None" or its number of lines (amended 2026-10-09: CSS adds to global, so it has no Global/Custom switch; its screen is the `TextareaControl` alone, and emptying it removes the layout's CSS);
  - an intro line: "Only this layout. Groups on Global follow the Design page. A block's own styles still win.";
  - the footer link "Edit global design ↗".
- **Group screen:** a Back button and the group title, then a `ToggleControl` "Custom for this layout". While the group differs from the layout as last saved, the header also shows a ↺ "Revert to saved" button that puts the group back as saved, Global or Custom, without a confirm (added 2026-10-09).
  - **Off:** a summary of the global values (colour swatches; font · size; style · size · radius).
  - **On:** the section's controls, seeded from global, with `enabled` set to true: Custom always prints the group, and there is no "Theme default" switch (amended 2026-10-09: Global already covers following the Design page). The controls are colours (`ColorPicker` ×4), font (`SelectControl` from `FONT_FAMILIES`), base size (`RangeControl` 10–24), input style and size (`ToggleGroupControl`), and radius (`RangeControl` 0–24).
- Turning a Custom row off opens a `Modal` listing each changed value with its global value. Confirm and Cancel.
- Footer link "Edit global design ↗": if `isEditedPostDirty()`, a confirm `Modal` asks to discard first, then sets `window.top.location.href` to the SPA's Design page (amended 2026-10-09: WordPress 7's `Document-Isolation-Policy` on the block editor blocks setting the parent's `hash`).
- Free: the same toolbar button and sidebar, whose root shows a Pro tag, one line of copy, and an Upgrade button to `editorData.upgradeUrl`. No group screens, no meta.

**Edge cases and failure modes:**
- **Global section off while the layer section is on:** the layer wins and prints its variables. **Layer section with `enabled: false`** (only from an import or a direct `wp/v2` write, since the sidebar always writes true): that section prints nothing for L even when global is on. Editing a value in the sidebar sets it back to true.
- **A section added to the schema later:** layers lack it, so it follows global. **A key added to an existing section:** `project_layer` drops the stored section as incomplete, so it falls back to global. The editor shows that group on Global again. Acceptable, and stated in the release note of such a change.
- **Editor opened on a layout with a stored but now-invalid section:** the sidebar shows Global for it. The next Save omits it.
- **Publish guard:** a live layout whose `publish_error()` objects cannot be saved, so its design change cannot be saved either. This is the existing rule and nothing new.
- **Two tabs editing the same layout:** the last Save wins, as for block content.
- **Custom CSS on the canvas:** not shown. The CSS row says "Shown on the storefront and in preview, not here".
- **Free import of a Pro export:** import is Pro only, so this does not occur. Free duplicate copies the meta harmlessly.

**Decision record:** the implementing PR adds `docs/decisions/2026-10-09-layout-design-override-layer.md`. It records the per-group layer in layout meta, written through core's REST from the editor, and applied by a Pro filter. It also updates `docs/architecture.md` (writers: core `wp/v2` writes `_yaycheckout_design`; storefront: effective design).

## Alternatives considered

| Option | Why rejected |
|---|---|
| Per-token layer with Global/Overridden rows and ↺ (YC-04) | Too fine for the editor's narrow sidebar. Per-group switches are clearer and the storage stays the same shape. |
| Copy all tokens into meta when override is on | The layout would stop following global changes, against the mock's "layer, not a copy". |
| One option `yaycheckout_design_overrides` keyed by layout id | Not layout meta. It leaves stale entries when a layout is deleted and needs its own duplicate, export and import plumbing. |
| Layout picker on the Design page (SPA) | Mock-only preview instead of the real canvas, and a second place to edit a layout. Replaced by the editor sidebar. |
| `PluginDocumentSettingPanel` in the Layout tab beside Import / Export | Cramped under Summary and Import / Export, and lost whenever a block is selected (the sidebar flips to the Block tab). A toolbar button with its own sidebar matches core's Styles, which merchants already know. |
| `__experimentalNavigator*` for the screens | Experimental on the 6.5 floor. Two levels of local state do the same job. |
| Custom Pro REST route for the layer | Separate save from the layout's Save, so dirty state and undo would diverge. Core's meta save keeps them together. |

## Rollout

- No migration or setting. The feature appears for Pro after upgrade, and every layout starts fully on Global.
- Release note: "Pro: give a layout its own colours, typography, inputs or CSS from the Design button in its editor."
- If `alan/feat/design-page-tokens` has not merged, the implementation branch rebases on it first, so the sections are `colours`, `typography` and `inputs`.

## Open questions

None.
