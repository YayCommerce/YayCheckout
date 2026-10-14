# Phase 2: Editor

Plan status, constraints and the interface contract are in [README.md](README.md).

**Depends on:** Phase 1 merged into the branch:
- `shared/visibility-rules.ts`: `normalize`, `isVisible`, `ATTRIBUTE`, `EXCLUDED`;
- the attribute registered on every block by `visibility/index.tsx`;
- `BlockVisibility` on the storefront.

**Exit criterion:** spec criteria 10–13 pass. The last is the end-to-end walk: a pinned bar with a Step Button set to "Hide when last step" and Place Order set to "Show when last step" shows Continue on steps 1–3 and Place Order on step 4, at 390px and 1280px, and an order places with Cash on delivery on an active copy of the layout.

Steps are expanded when Phase 1 lands. Tasks and interfaces are fixed now.

---

### Task 4: Visibility panel, currency symbol and Conditional marker

**Files:**
- Modify: `packages/blocks/src/visibility/index.tsx`: the panel and the marker.
- Create: `packages/blocks/src/visibility/editor.css`: the marker tag.
- Modify: `includes/Blocks/BlockRegistry.php`: `'currencySymbol' => html_entity_decode( get_woocommerce_currency_symbol() )` in `$editor_data`. Also enqueue `visibility/index`'s CSS on `enqueue_block_assets`, in the layout editor only, as `enqueue_live_value_editor_style()` does.
- Modify: `packages/blocks/src/shared/editor-data.ts`: `currencySymbol: string`.

**Interfaces:**
- Consumes: `normalize`, `ATTRIBUTE`, `EXCLUDED` (README contract); `useIsLayoutEditor()` (`shared/layout-editor.ts`); `editorData.currencySymbol`.
- Produces:
  - `VisibilityPanel`, an `editor.BlockEdit` HOC, layout editor only, for selected blocks not in `EXCLUDED`.
  - The editor class `is-yaycheckout-conditional` (via `editor.BlockListBlock`) on any block whose `normalize( attributes[ ATTRIBUTE ] )` is non-null.
  - `stepOptions(): { value: number | 'last'; label: string }[]`: "1. <title>" … plus "Last step", from the first `yaycheckout/steps` in the editor. Task 5 reuses it.
- Behaviour, from the spec's UI section:
  - **Visibility select:** *Always*, *Show when…* or *Hide when…*. *Always* sets the attribute to `undefined`.
  - **Rule rows:** each row has a type select that lists only unused types, plus a remove button. Step and Subtotal rows get their own parameter controls.
  - **Subtotal amount:** a NumberControl, min 0, step 0.01, suffixed with `currencySymbol`.
  - **Summary line** and the **two notices**, worded exactly as in the spec.
- Verify: spec criteria 10 and 11.
  - The panel is absent in the post editor and for Checkout, Steps and Step.
  - Settings survive save and reopen.
  - The marker shows on hover and selection.
  - `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` passes, because `editor-data.ts` is shared with the SPA.

### Task 5: Visibility preview sidebar

**Files:**
- Modify: `packages/blocks/src/visibility/index.tsx`: a `PluginSidebar` with an eye icon, pinned, plus a `PluginSidebarMoreMenuItem`, following `checkout/layout-design.tsx:608`.
- Modify: `packages/blocks/src/visibility/editor.css`: `.is-yaycheckout-preview-hidden { opacity: .35; outline: 1px dashed currentColor; }`.

**Interfaces:**
- Consumes: `isVisible`, `stepOptions()` (Task 4).
- Produces:
  - Editor-only state `{ open: boolean; step: 'off' | number | 'last'; logged_in: boolean; needs_shipping: boolean; has_coupon: boolean; subtotal: number }`, held in a `@wordpress/data` store `yaycheckout/visibility-preview` (`createReduxStore` + `register` in `index.tsx`). It has the selector `getPreview()` and the actions `setPreview( partial )` and `resetPreview()`. It lives in memory only, never in the post. `open` follows the sidebar, and closing it calls `resetPreview()`.
  - The class `is-yaycheckout-preview-hidden` on conditional blocks that fail `isVisible()` while the sidebar is open and any input is set. "Last" maps to `step = stepCount`.
- Verify: spec criterion 12.
  - Dimming matches the storefront for the same inputs.
  - Blocks stay selectable.
  - Closing the sidebar clears the dimming.
  - `wp.data.select('core/editor').isEditedPostDirty()` stays `false`.

### Task 6: Docs, decision record and end-to-end

**Files:**
- Create: `docs/decisions/2026-10-13-block-visibility-in-block-markup.md`. It records that visibility is a block attribute evaluated by a PHP owner with a TypeScript mirror. The rejected alternatives come from the spec.
- Modify: `docs/architecture.md`, in the rendering paragraph (~:63): one sentence on `BlockVisibility` (the attribute, `HIDDEN_CLASS`, the state element and fragment, the live script).
- Modify: `docs/plans/2026-10-13-block-visibility-conditions/README.md`: Status `done YYYY-MM-DD` in the PR that finishes it.

**Interfaces:** none new.

- Verify: the plan's exit criterion above (spec criterion 13).
