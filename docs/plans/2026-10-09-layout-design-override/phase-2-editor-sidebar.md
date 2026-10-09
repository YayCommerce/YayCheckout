# Phase 2: Design sidebar in the editor

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** Phase 1 Tasks 1–3 (the REST meta field and the effective print point in the canvas)
**Delivers:** The toolbar's Design button and sidebar: group list, Global/Custom per group, controls, live canvas, revert confirm, "Edit global design ↗" and the Free teaser. Plus the decision record and the architecture update.
**Exit criterion:** the spec's acceptance criterion 13, on yaycheckout.test (Pro):
1. Open a layout's editor and click the toolbar's Design button.
2. Set Colours to Custom with primary `#171719`. Set Inputs to Custom with Underline and radius 0. Save.
3. Open the layout's preview link. The Place order button is `#171719`, and the fields are underlined with square corners.
4. On the Design page, set global base size to 18 and save. Reload the preview. Text is 18px, and the custom colours and inputs remain.
5. In the editor, set Colours back to Global and Save. The preview button returns to the global primary.

### Task 1: Editor data filter and the global design for the editor

**Files:**
- Modify: `includes/Blocks/BlockRegistry.php` (`$editor_data`)
- Modify: `includes/Pro/ProServiceProvider.php`
- Modify: `packages/blocks/src/shared/editor-data.ts` (type `EditorData` gains an optional `design`)

**Interfaces:**
- Produces: the filter `yaycheckout_editor_data`, and `editorData.design` (see the README contract).

Steps: expand after Phase 1 lands.

### Task 2: CSS variables builder and live canvas style

**Files:**
- Create: `packages/blocks/src/shared/design-vars.ts`
- Create: `packages/blocks/src/checkout/use-live-design-style.ts`, with `useLiveDesignStyle( tokens ): void`. It writes `<style id="yaycheckout-layout-design-live">:root{…}</style>` into the document of `iframe[name="editor-canvas"]`, falling back to `document`, and removes it on unmount.

**Interfaces:**
- Consumes: `editorData.design`.
- Produces: `designVars()` (see the README contract) and `useLiveDesignStyle()`.

Steps: expand after Phase 1 lands. The verification must cover a layer section with `enabled: false` while global is on (set through `wp/v2`, since the sidebar always writes true): the canvas falls back to the base CSS defaults.

### Task 3: The Design sidebar

**Files:**
- Create: `packages/blocks/src/checkout/layout-design.tsx`. It calls `registerPlugin( 'yaycheckout-layout-design', … )`, renders a `PluginSidebar` titled "Design" with icon `styles`, holds the root and group screens in local state, and edits the meta through `editPost( { meta: { _yaycheckout_design } } )`.
- Create: `packages/blocks/src/checkout/layout-design.css`
- Modify: `packages/blocks/src/checkout/index.ts` (import `./layout-design`, next to `./import-export`)

**Interfaces:**
- Consumes: `editorData.design`, `editorData.isPro`, `editorData.upgradeUrl`, the REST meta field, and `useLiveDesignStyle()`.
- Produces: nothing consumed later.

Steps: expand after Phase 1 lands.
- The controls come from `editorData.design.schema`: `hex_color` → `ColorPicker`, `int` → `RangeControl` with the schema's `min`/`max`, `enum` → `ToggleGroupControl`, or `SelectControl` for `font_family` (labels from `font_families`). `enabled` has no control: Custom writes it as true.
- Labels per section and key are the only copy defined in TypeScript.
- Free renders the same button with a root holding a Pro tag, one line of copy, and an Upgrade button to `editorData.upgradeUrl`.

### Task 4: Revert confirm and "Edit global design ↗"

**Files:**
- Modify: `packages/blocks/src/checkout/layout-design.tsx`

**Interfaces:**
- Consumes: the editor store's `isEditedPostDirty()`.

Steps: expand after Phase 1 lands.
- Turning Custom off opens a `Modal` that lists each differing value as "Label old → global".
- Custom CSS has no Custom switch: its screen is a `TextareaControl`, an empty value removes `custom_css`, and its root row shows "None" or the line count.
- The link confirms first when the post is dirty, then sets `window.top.location.href` to `editorData.design.page_url` (the SPA's `#/design`). Not `hash`: WordPress 7 isolates the block editor (`Document-Isolation-Policy`), so the SPA around the embedded editor is cross-origin to it, and `href` is the one Location setter allowed across origins.
- The group header's ↺ "Revert to saved" shows while the group differs from `getCurrentPostAttribute( 'meta' )`, and restores that group as saved.

### Task 5: Decision record and architecture

**Files:**
- Create: `docs/decisions/2026-10-09-layout-design-override-layer.md`, following `docs/decisions/2026-10-05-decision-record-practice.md`.
- Modify: `docs/architecture.md`:
  - writers: core `wp/v2` writes `_yaycheckout_design`, Pro only;
  - storefront: `CheckoutBaseStyles` prints `DesignRepository::for_layout()`;
  - Free/Pro: the filter `yaycheckout_design_for_layout`.

Steps: expand after Phase 1 lands.
