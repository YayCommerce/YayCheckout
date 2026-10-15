# Guest View Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-15
**Goal:** A merchant designs what a blocked guest sees (guest checkout off and "During checkout" account creation off) in a post of its own, a child of the layout, edited in core's block editor behind a header switch.
**Spec:** [docs/specs/2026-10-14-guest-view-design.md](../specs/2026-10-14-guest-view-design.md)
**Architecture:** The guest view is the `post_content` of a published `yay_checkout_guest` post whose `post_parent` is the layout, so the layout's `post_content` stays the form and nothing that reads it changes. The server renders it in the `must_log_in()` branch of `checkout/render.php`. The editor needs no code of its own: the header switch opens the other post in the same frame, and each post gets core's toolbars, List View, inspector, undo, autosave and revisions. This replaces two uncommitted versions: a block in the checkout markup, then a layout meta in a nested editor.
**Touches:** `includes/Models/Layout.php`, `includes/Services/LayoutMarkupReader.php`, `includes/PostTypes/LayoutPostType.php`, `includes/Repositories/LayoutRepository.php`, `includes/Controllers/LayoutTemplateCatalogue.php`, `includes/Controllers/LayoutCoreRestController.php`, `includes/Blocks/BlockRegistry.php`, `includes/Engine/Admin/LayoutEditorCanvas.php`, `includes/Renderer/{CheckoutLogin,PreviewRequest}.php`, `includes/Pro/Controllers/{LayoutExportRestController,LayoutImportRestController}.php`, `includes/Pro/Services/LayoutImportReview.php`, `packages/blocks/src/checkout/{render.php,layout-actions.tsx,layout-view.tsx,layout-view.css}`, `packages/blocks/src/login/{render.php,edit.tsx,editor-data.ts}`, `packages/blocks/src/shared/editor-data.ts`, `templates/layouts/guest-view.php`, `uninstall.php`, `docs/`. Shared: `LayoutRepository::insert_draft()` (consumers: `create`, `duplicate`, `import`), `CheckoutLogin::must_log_in()` (consumers: `checkout/render.php`, `coupon-form/render.php`), `BlockRegistry::editor()` (consumers: `allowed_blocks()`, `register_category()`, the live value and visibility editor assets).
**Tests:** none. CLAUDE.md's test rule covers `publish_error()`, `pick()`, `RequiredFieldOverlay::apply()` and `LayoutImportReview`'s review; this change adds a field to the envelope but not a rule of the review, and the suite holds no tests. Each task has an exact manual check instead.

## Global constraints

- Product PHP must run on PHP 7.4 (and the rest of `.claude/rules/php-standards.md`).
- Never hand-edit generated output (`assets/dist/`); build blocks with `pnpm build:blocks` (run `source ~/.nvm/nvm.sh && nvm use` first: Node 22).
- Free and Pro both ship it. Nothing in Free may reference `YayCheckout\Pro`.
- Storage is one post type, `Layout::GUEST_VIEW_POST_TYPE` (`yay_checkout_guest`). No option, table, meta or new REST route. `Layout::to_array()` and the SPA's layout wire schema do not change.
- Editor code uses only stable WordPress APIs: no nested `BlockEditorProvider`, no `__experimental` or `__unstable` component.
- Storefront changes stand down when `CheckoutPage::get_instance()->layout()` is null.
- The preview argument is `yaycheckout_view=guest`, read only with a valid preview token.
- Models and Services make no WordPress state reads or writes.
- Branch `alan/feat/guest-checkout-layout` from `dev/edd`. Commit only when asked. `composer.json` was modified before this work: never stage it.

**Setup S** (manual checks): WordPress at `https://yaycheckout.test`, Safe mode off, layout 677 active, WooCommerce > Settings > Accounts: guest checkout off, "During checkout" off, "Enable log-in during checkout" off. Restore layout 677's guest view to the default after any check that changes it.

## Review focus

- **A disallowed block written by any path** is stripped (Task 1 step 2).
- **Anonymous REST** cannot read guest view posts (Task 1 step 3).
- **Delete** removes the guest view post; core would not (Task 1 step 2).
- **Guest view posts stay out of the layouts list and the Free limit** (Task 1 step 2).
- **Switch keeps the embed flag** after Gutenberg rewrites the URL (Task 3 step 3).
- **Unsaved changes** are saved or the switch is cancelled (Task 3 step 3).
- **Not blocked, or logged in:** the guest view shows nowhere (Task 2 step 2).

---

### Task 1: Storage, sanitizer, repository and Pro export/import

**Files:** `includes/Models/Layout.php`, `includes/Services/LayoutMarkupReader.php`, `includes/PostTypes/LayoutPostType.php`, `includes/Repositories/LayoutRepository.php`, `includes/Controllers/LayoutTemplateCatalogue.php`, `includes/Pro/Controllers/LayoutExportRestController.php`, `includes/Pro/Controllers/LayoutImportRestController.php`, `includes/Pro/Services/LayoutImportReview.php`, `templates/layouts/guest-view.php`, `uninstall.php`

**Interfaces:**
- `Layout::GUEST_VIEW_POST_TYPE = 'yay_checkout_guest'`; `Layout::GUEST_VIEW_BLOCKS` (the 12 names of spec criterion 6).
- `LayoutMarkupReader::sanitize_guest_view( string $markup ): string` (static).
- `LayoutPostType::sanitize_guest_view( $data )` on `wp_insert_post_data`.
- `LayoutRepository::guest_view_id( int $layout_id ): int`, `guest_view( int $layout_id ): string`, `save_guest_view( int $layout_id, string $markup ): int`; `insert_draft( …, string $template_slug, string $guest_view )`; `import( …, LayoutDesign $design, string $guest_view )` (the import controller always passes the file's value, `''` when it has none).
- `LayoutTemplateCatalogue::guest_view(): string`.
- Export envelope: `layouts[0].guest_view`.

- [x] **Step 1:** Implement the interfaces. Register the post type with `show_ui`, `show_in_rest`, `rest_controller_class` `LayoutCoreRestController`, supports `title, editor, revisions`, labels "Guest views" / "Guest view" and the layout's capabilities. `save_guest_view()` creates a published post titled "Guest view" (a draft's autosave would overwrite the live guest view) with `post_parent`, or calls `wp_save_post_revision()` then updates. `delete()` deletes the guest view post after the layout. `uninstall.php` selects both post types.
- [x] **Step 2: Verify storage** with `wp eval-file` as user 1: create a layout from `classic-two-column` → one published guest view post with the layout as parent and 269 characters; `save_guest_view()` with a paragraph, a `yaycheckout/field` and a `core/html` `<script>` → only the paragraph stays; `reset()` → same post id, default content, revisions grow; `duplicate()` → a new guest view post with equal content; archive and `delete()` both → their guest view posts are gone; `count()` unchanged by guest view posts.
- [x] **Step 3: Verify REST:** `curl -sk "https://yaycheckout.test/?rest_route=/wp/v2/yay_checkout_guest/<id>"` and the collection, logged out → 401 both.
- [x] **Step 4: Verify Pro export/import:** export a layout from the SPA, check `layouts[0].guest_view` equals `guest_view( <id> )`; import the file → the new layout's guest view equals it; remove `guest_view` from the file and import → no guest view post.

### Task 2: Storefront render

**Files:** `packages/blocks/src/checkout/render.php`, `packages/blocks/src/login/render.php`, `includes/Renderer/CheckoutLogin.php`, `includes/Renderer/PreviewRequest.php`

**Interfaces:** `CheckoutLogin::render_guest_view( string $markup ): string`, `CheckoutLogin::in_guest_view(): bool`, `PreviewRequest::VIEW_ARG`, `PreviewRequest::guest_view(): bool`.

- [x] **Step 1:** In the `must_log_in()` branch, read `LayoutRepository::guest_view( $layout->id )`; when not blank, print it through `render_guest_view()` in `<div class="yaycheckout-checkout yaycheckout-checkout--guest" data-yaycheckout-layout>` and return; otherwise today's output.
- [x] **Step 2: Verify:** Setup S, logged out with a product in the cart, `curl` `/?page_id=8` → `yaycheckout-checkout--guest` 1, "Log in to continue" 1, `name="checkout"` 0, "must be logged in" 0. The not-blocked and logged-in cases were checked on the meta version with the same render branch.

### Task 3: Editor — the view switch

**Files:** `includes/Blocks/BlockRegistry.php`, `includes/Engine/Admin/LayoutEditorCanvas.php`, `packages/blocks/src/checkout/{layout-view.tsx,layout-actions.tsx}`, `packages/blocks/src/login/{edit.tsx,editor-data.ts}`, `packages/blocks/src/shared/editor-data.ts`

**Interfaces:**
- `window.yayCheckoutBlocks.guestView = { layoutId, guestId, layoutUrl, guestUrl }` (URLs keep `LayoutEditorEmbed::QUERY_FLAG` on an embed request) (`BlockRegistry::guest_view_posts()`), in a layout's or a guest view's editor only; `window.yayCheckoutLogin.guestPostType`.
- `BlockRegistry::editor()` returns `'guest'` for the guest post type.
- `layout-view.tsx`: `GUEST_VIEW_ARG`, `useIsGuestView()`, `ViewSwitch`.

- [x] **Step 1:** `allowed_blocks()` returns `GUEST_VIEW_BLOCKS` in the guest editor and the YayCheckout category shows there. `LayoutEditorCanvas` gives the guest editor the parent layout's design, hides its title and core's publish controls, and leaves the theme's widths.
- [x] **Step 2:** `ViewSwitch`: two buttons, the current one pressed; opens the other post's URL from `guestView` with `location.replace()`; with unsaved changes a modal offers Cancel and "Save and switch"; in the guest editor an info notice. `layout-actions.tsx` mounts in both editors; in the guest editor it drops Activate and Deactivate and previews the parent layout with `&yaycheckout_view=guest`.
- [x] **Step 3: Verify in the browser** (Playwright, 1400px): open `post.php?post=677&action=edit&yc-embed=1` → `guestView` holds the layout and guest ids; click "Guest view" → the guest post opens with the embed body class, buttons "Checkout form", "Guest view" (pressed), Preview, Save, the 12 allowed blocks, the notice, and the native toolbar and Block inspector; type in the heading, click "Checkout form" → the modal; "Save and switch" → layout 677 opens with "Checkout form" pressed, and `guest_view( 677 )` holds the edit; Preview from the guest editor → URL with `yaycheckout_view=guest`, the banner's "Showing the Guest view.", the heading, no form.
- [x] **Step 4: Verify smoothness** (Playwright, from the layouts list): the switch shows a busy button and an "Opening the …" overlay until the other editor loads; three switches then one Close closes the dialog (`location.replace()` adds no history entry); a forced autosave in the guest editor leaves `guest_view( 677 )` unchanged and writes an autosave revision; a REST write holding `yaycheckout/place-order` and a `core/html` `<script>` stores only the allowed blocks; Cancel in the save modal keeps the guest editor; logging in through the guest view's Login modal reloads into the checkout form.

### Task 4: Docs

**Files:** `docs/decisions/2026-10-14-guest-view-in-child-post.md` (new; the meta version's record, never committed, is deleted), `docs/architecture.md`

- [x] **Step 1:** Write the decision record.
- [x] **Step 2:** Update `docs/architecture.md`: the writers line and the Guest view bullet.
- [x] **Step 3: Verify:** `git grep -n "META_GUEST_VIEW\|_yaycheckout_guest_view" -- docs/architecture.md docs/decisions` → no hits.

### Task 5: End-to-end and handoff

- [x] **Step 1:** Run the spec's end-to-end verification (criterion 17), at 390px and 1280px wide, then restore the WooCommerce options and delete the test layout.
- [x] **Step 2:** `composer lint -- <every changed PHP file>` and `pnpm build:blocks`: no new findings, build succeeds. `git status --short`: only the files this plan names; `composer.json` unstaged.
- [x] **Step 3: Commit** (when asked). Stage the files in Touches by name. Message: `feat(layouts): design the checkout guests see when they must log in`.
