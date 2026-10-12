
# Order Note Editor Styles Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract.

**Status:** done 2026-10-09
**Goal:** the Order note and Additional fields blocks load their own editor stylesheets, so their editor previews no longer depend on the Field block's stylesheet.
**Spec:** none, planned from the request (the minor points of the `/yayplancheck` review of `docs/plans/2026-10-09-order-note-blocks.md`)
**Architecture:** both blocks' `edit.tsx` import CSS (`FieldPreview`'s `field-preview.css`, and for Order note `disclosure.css`). So `pnpm build:blocks` already emits `assets/dist/blocks/<block>/index.css`, but nothing enqueues it, because neither `block.json` declares `editorStyle`. Every other block whose build emits an `index.css` declares `"editorStyle": "file:./index.css"`. The previews look right today only because WordPress enqueues every registered block's editor styles, and Field's `index.css` holds the same shared rules. That is byte for byte true for Order note, whose `index.css` has the same md5 as Field's. The fix is the declaration the other blocks use; no CSS changes.
**Touches:** `packages/blocks/src/order-note/block.json`, `packages/blocks/src/additional-fields/block.json`
**Tests:** none. CLAUDE.md's test rule covers checkout logic and reproduced bugs. This is an editor stylesheet declaration, so the task ends in an exact manual check instead.

## Requirements

- **Goal:** each block's editor preview is styled by its own registered stylesheet.
- **Decided: no change for the review's other minor points.** `docs/plans/2026-10-09-order-note-blocks.md` is `done`, so it is kept as a record and not edited. The three points are:
  1. Files in the diff that the plan doesn't list: the comment updates in `includes/Services/Disclosure.php` and `packages/blocks/src/shared/disclosure-view.ts`, and the new `packages/blocks/src/order-note/defaults.ts`. They came from the `/yaytidy` pass and are committed in `87168a8`, whose message records them.
  2. `additional-fields/render.php` uses `LayoutMarkupReader::has_order_note()` instead of the plan's `order_note() !== null`. Both give the same answer, and the reviewer checked both cases. `has_order_note()` also avoids parsing the layout.
  3. `RequiredFieldOverlay::apply_order_note()` restores a missing `order_comments` through `restore_missing_field()`, so the entry also carries `validate => []` and the block's placeholder. That is intended: there is one "add back a dropped field" path, and those keys change no validation for a textarea.
- **Out of scope:** any CSS change, and the blocks without an `index.css` in `assets/dist/blocks/` (back-button, billing, login, and the others), which have no editor stylesheet to declare.
- **Acceptance criteria:**
  1. `packages/blocks/src/order-note/block.json` and `packages/blocks/src/additional-fields/block.json` each declare `"editorStyle": "file:./index.css"`, placed after `editorScript`, as in `field/block.json` and `create-account/block.json`.
  2. After `pnpm build:blocks`, each block's registered `editor_style_handles` is a single handle, whose `src` ends with `assets/dist/blocks/<block>/index.css`.
  3. End to end: in the layout editor, an inserted Order note and Additional fields render their previews with the same styling as before, and the console shows no block-validation warning.

## Global constraints

- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/<block>/block.json` and run `pnpm build:blocks`.
- Run commands on Node 22 (`nvm use`). Use pnpm only.
- Git: work on `trace/feat/disclosure-checkbox`, the branch holding the blocks, so the fix ships in the same PR into `dev/edd`. Commit messages follow Conventional Commits and end with the `Co-Authored-By` trailer CLAUDE.md names. Stage files by name.
- Base checks: `pnpm build:blocks` and `composer lint` pass on the base branch. `pnpm --filter @yaycheckout/admin-page lint` already fails there, and this plan doesn't touch the SPA.

## Review focus

- **The same CSS registered twice.** Order note's `index.css` matches Field's, so the editor may load identical rules twice. That is harmless, and it is what every pair of blocks sharing `FieldPreview` already does (for example Field and Extra field). The check is that the previews look unchanged (Task 1, Step 3).

---

### Task 1: Declare both blocks' editor stylesheets

**Files:**
- Modify: `packages/blocks/src/order-note/block.json`, `packages/blocks/src/additional-fields/block.json`

**Interfaces:**
- Produces: the style handles `yaycheckout-order-note-editor-style` and `yaycheckout-additional-fields-editor-style`, which core names through `generate_block_asset_handle()`.

- [ ] **Step 1: Add `editorStyle` to both `block.json` files**

Insert `"editorStyle": "file:./index.css",` on the line after `"editorScript": "file:./index.js",`, matching `field/block.json`.

- [ ] **Step 2: Build, then check the registration**

1. Run `pnpm build:blocks`. Expect `compiled successfully`, with `assets/dist/blocks/order-note/block.json` and `assets/dist/blocks/additional-fields/block.json` each containing `"editorStyle": "file:./index.css"`.
2. With WP-CLI on the site, run `wp eval 'foreach ( [ "order-note", "additional-fields" ] as $b ) { $t = WP_Block_Type_Registry::get_instance()->get_registered( "yaycheckout/$b" ); $h = $t->editor_style_handles; echo $b, " ", implode( ",", $h ), " ", wp_styles()->registered[ $h[0] ]->src ?? "none", "\n"; }'`. Expect each line to name `yaycheckout-<block>-editor-style` and a `src` ending in `assets/dist/blocks/<block>/index.css`.

- [ ] **Step 3: Verify in the editor**

Open the active layout in the layout editor (`/wp-admin/post.php?post=<id>&action=edit`) and insert Order note and Additional fields under Checkout. Expect:
- the network panel, or the editor iframe's `<head>`, to load `order-note/index.css` and `additional-fields/index.css`;
- Order note's preview to show the textarea, the label and the disclosure toggle styled as before;
- Additional fields to show the textarea preview and the info notice;
- no block-validation warning after a reload.

Don't save the layout.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/order-note/block.json`, `packages/blocks/src/additional-fields/block.json`
Message: `fix(blocks): load the Order note and Additional fields editor styles`
