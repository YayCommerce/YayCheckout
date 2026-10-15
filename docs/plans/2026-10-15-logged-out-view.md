# Logged-out Checkout View Implementation Plan

> **For agentic workers:** do the tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the Requirements section below is the source.

**Status:** done 2026-10-15
**Goal:** When WooCommerce makes guests log in before checkout, a merchant can design a second "Logged out" view of each layout in the editor, and guests see that view.
**Spec:** none; planned from the request.
**Architecture:** The logged-out markup lives in post meta `_yaycheckout_logged_out_content`. The editor stays core's post editor; the URL arg `yc-view=logged-out` tells it which view to open. In that view a REST header makes `LayoutCoreRestController` serve the meta as `content` and write `content` back into the meta, so core's load, save and dirty tracking work as they are, with no new endpoint and no second editor store. On the storefront, `CheckoutPage::resolve()` swaps `$layout->content`, so every existing reader (fragments, fields, visibility) sees the same markup with no per-reader change.
**Touches:**
- `includes/Models/Layout.php`
- `includes/Engine/Admin/LayoutEditorEmbed.php`
- `includes/Controllers/LayoutCoreRestController.php`
- `includes/Repositories/LayoutRepository.php` (shared: `insert_draft()` is used by create, duplicate and import, and gets only an optional trailing parameter)
- `includes/Blocks/BlockRegistry.php`
- `includes/Renderer/CheckoutPage.php`
- `includes/Renderer/CheckoutLogin.php`
- `packages/blocks/src/checkout/{render.php,layout-actions.tsx,publish-guard.ts,import-export.tsx}`
- new `packages/blocks/src/checkout/layout-view.tsx`
- `packages/blocks/src/login/render.php`
- `packages/blocks/src/shared/editor-data.ts`

**Tests:** none. The user chose manual checks.

## Requirements

- **Goal:** A guest who must log in sees the merchant's logged-out view. Everyone else sees the main layout.
- **Out of scope:**
  - Pro export and import of the logged-out view: add a backlog item with `/yaybacklog`.
  - Revisions for the logged-out view. Like the design meta, it has none.
  - Reset to template.
  - Any SPA (`apps/admin`) change.
- **Acceptance criteria:**
  1. While WooCommerce requires login (guest checkout off and account creation at checkout off), the editor header shows a "Logged in / Logged out" toggle. Otherwise the toggle is hidden, unless the editor is already in the logged-out view.
  2. The first time the merchant opens the logged-out view, it shows a starter: a Checkout block holding a heading, the "You must be logged in to checkout." paragraph and a Login block.
  3. Saving in the logged-out view writes only `_yaycheckout_logged_out_content`. `post_content` is unchanged.
  4. Switching views with unsaved edits opens a "Save changes before switching?" modal with Cancel and Save and switch. Every switch shows a loading state until the other view has loaded.
  5. On the storefront, a guest for whom `CheckoutLogin::must_log_in()` is true sees the saved logged-out view. Logged-in shoppers, and guests who may check out or create an account at checkout, see the main layout. A layout with no saved logged-out view renders as it does today.
  6. End to end: with login required, save a logged-out view. A guest in a private window sees it, logs in through its Login block, and lands on the main checkout form.
- **Decided with the user:**
  - Trigger: `must_log_in()`. With account creation on, WooCommerce itself shows guests the full form and requires the account fields, so they keep the main layout. The user asked which case WooCommerce treats as the logged-out one; this is it.
  - Toggle visibility: only while the setting makes guests log in.
  - Default: a minimal Login starter. The storefront falls back to the main layout until the merchant saves a logged-out view.
  - Tests: none, manual checks.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in `boot_pro()`. Everything here is Free and must not reference `YayCheckout\Pro`.
- Never hand-edit `assets/dist/`. Rebuild with `pnpm build:blocks`.
- Commits follow Conventional Commits and end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Stage files by name.
- Keep the diff small: no new REST route, no new data store, no SPA change.
- Test only on a new or duplicated layout. Autosave writes edits into an existing draft.

## Review focus

- **Guest-only swap.** A logged-in shopper, including the merchant browsing the shop, must never get the logged-out view: the swap is gated on `must_log_in()`, and outside a preview link only. (Task 3, step 4)
- **Save in logged-out view must not overwrite `post_content`.** That would replace the real checkout with the login page for every shopper. (Task 1, step 4)
- **Kses.** A user without `unfiltered_html` must not be able to store raw script in the meta, because the meta bypasses core's `content_save_pre`. (Task 1, step 2)
- **Editor preload.** post.php preloads the post over REST without the header, so the view must also be read from `$_GET`. Otherwise the canvas opens on the main markup and the first save overwrites the meta with it. (Task 1, step 4)
- **Login in the logged-out view.** The Login block must render even when WooCommerce's "log in at checkout" reminder is off; otherwise the guest has no way in. (Task 3, step 4)

---

### Task 1: Store the logged-out view and serve it to the editor over REST

**Files:**
- Modify: `includes/Models/Layout.php`
- Modify: `includes/Engine/Admin/LayoutEditorEmbed.php`
- Modify: `includes/Controllers/LayoutCoreRestController.php`
- Modify: `includes/Repositories/LayoutRepository.php`

**Interfaces:**
- Produces:
  - `Layout::META_LOGGED_OUT = '_yaycheckout_logged_out_content'`
  - `Layout::$logged_out_content` (string, `''` when none), read in `from_post()` and kept out of `to_array()`.
  - `LayoutCoreRestController::logged_out_starter(): string` (private static): the starter markup, with translatable text. It lives on the controller, its only reader, because `Layout` makes no WordPress calls.
  - `LayoutEditorEmbed::QUERY_VIEW = 'yc-view'`, `LayoutEditorEmbed::VIEW_LOGGED_OUT = 'logged-out'` and `LayoutEditorEmbed::VIEW_HEADER = 'X-YayCheckout-View'`.
  - `LayoutEditorEmbed::is_logged_out_view( ?\WP_REST_Request $request = null ): bool`: true when the header equals `VIEW_LOGGED_OUT`, or, failing that, when `$_GET[ QUERY_VIEW ]` does. The `$_GET` fallback covers post.php's REST preload.

- [ ] **Step 1: Add the meta constant, property and `from_post()` read to `Layout`, and `logged_out_starter()` to `LayoutCoreRestController`.** Build the starter from the Checkout block opener a template uses (copy one from `templates/layouts/*/markup.php`). Inside it: `core/heading` "Log in to check out", `core/paragraph` with WooCommerce's message `__( 'You must be logged in to checkout.', 'woocommerce' )` (as `checkout/render.php` does), and `<!-- wp:yaycheckout/login /-->`.
- [ ] **Step 2: Override `prepare_item_for_database( $request )` in `LayoutCoreRestController`, before the existing publish check.** When `LayoutEditorEmbed::is_logged_out_view( $request )` is true and `isset( $prepared->post_content )`, move the content into `$prepared->meta_input[ Layout::META_LOGGED_OUT ]` and `unset( $prepared->post_content )`. If `! current_user_can( 'unfiltered_html' )`, run it through `wp_unslash( wp_filter_post_kses( wp_slash( … ) ) )` first: the value is unslashed at that point (`update_item()` slashes afterwards), and kses expects slashed input. The existing check then falls back to the stored `post_content`, unchanged.
- [ ] **Step 3: Override `prepare_item_for_response( $item, $request )` in `LayoutCoreRestController`.** When `is_logged_out_view( $request )` is true and `'edit' === $request['context']`, set `content.raw` to the meta, or to `Layout::logged_out_starter()` when the meta is empty. Set `content.rendered` to `''`.
- [ ] **Step 4: Have `LayoutEditorEmbed::tag_rest_requests()` send the view header.** When `$_GET[ QUERY_VIEW ] === VIEW_LOGGED_OUT`, the inline apiFetch middleware also sends `VIEW_HEADER: "logged-out"`. Build the header object in PHP and `wp_json_encode` it, so the two headers stay in one middleware.
- [ ] **Step 5: Carry the meta in duplicate.** Add `string $logged_out_content = ''` as the last parameter of `LayoutRepository::insert_draft()` and put it in `meta_input` when it is not `''`. `duplicate()` passes `$source->logged_out_content`.
- [ ] **Step 6: Verify.**
  1. `composer lint -- includes/Models/Layout.php includes/Engine/Admin/LayoutEditorEmbed.php includes/Controllers/LayoutCoreRestController.php includes/Repositories/LayoutRepository.php` passes.
  2. Duplicate a layout, then open `/wp-admin/post.php?post=<ID>&action=edit&yc-embed=1&yc-view=logged-out`. Expect the starter on the canvas.
  3. Edit the heading and save. Expect `wp post meta get <ID> _yaycheckout_logged_out_content` to print the edited markup, and `wp post get <ID> --field=post_content` to be unchanged from before.
  4. Reopen without `&yc-view`. Expect the main layout.
  5. Duplicate that layout. Expect the copy to hold the same meta.
- [ ] **Step 7: Commit.** Stage the four files. Message: `feat(layouts): store a logged-out view per layout`

### Task 2: View toggle in the editor, with a save prompt and a loading state

**Files:**
- Create: `packages/blocks/src/checkout/layout-view.tsx`
- Modify:
  - `packages/blocks/src/checkout/layout-actions.tsx` (render the switch in the header `Flex`, before Preview)
  - `packages/blocks/src/checkout/publish-guard.ts`
  - `packages/blocks/src/checkout/import-export.tsx`
  - `packages/blocks/src/shared/editor-data.ts`
  - `includes/Blocks/BlockRegistry.php`
  - `includes/Renderer/CheckoutLogin.php`

**Interfaces:**
- Consumes: `LayoutEditorEmbed::QUERY_VIEW` and `VIEW_LOGGED_OUT` from Task 1.
- Produces:
  - `CheckoutLogin::login_required(): bool`: `! is_registration_enabled() && is_registration_required()`. `must_log_in()` becomes `! is_user_logged_in() && self::login_required()`.
  - `window.yayCheckoutBlocks.view`, typed in `editor-data.ts` as `EditorView = { current: 'main' | 'logged-out'; loginRequired: boolean; queryArg: string; urls: Record< 'main' | 'logged-out', string > }`. `urls` comes from `LayoutEditorEmbed::view_urls( int $post_id ): array`, because core rewrites the editor's address once loaded, dropping `yc-embed` and `yc-view`.
  - `.yaycheckout-layout-actions .components-toggle-group-control { white-space: nowrap; }` in `LayoutEditorCanvas::EDITOR_CSS`.
  - `LayoutViewSwitch` (default export of `layout-view.tsx`) and `isLoggedOutView(): boolean`.

- [ ] **Step 1: Add `login_required()` to `CheckoutLogin` and express `must_log_in()` through it.**
- [ ] **Step 2: Print the view data from `BlockRegistry::editor_design_data()`.** Use the same `inline_data()` call as `liveValues` to print `window.yayCheckoutBlocks.view`. `current` comes from `LayoutEditorEmbed::is_logged_out_view()`, and `loginRequired` from `function_exists( 'WC' ) && CheckoutLogin::login_required()`.
- [ ] **Step 3: Write `LayoutViewSwitch` in `layout-view.tsx`.**
  - It is a `ToggleGroupControl` with the options "Logged in" and "Logged out", rendered only when `loginRequired || current === 'logged-out'`.
  - Picking the other option with `isEditedPostDirty()` false opens the loading modal and then calls `window.location.assign()`, toggling `queryArg` on `window.location.href` with `addQueryArgs` / `removeQueryArgs` from `@wordpress/url`.
  - When the post is dirty, it first shows a `Modal` titled "Save changes before switching?" with Cancel and Save and switch. Save and switch is disabled with `accessibleWhenDisabled` while `isPostSavingLocked()`.
  - Save and switch calls `await savePost()`, then navigates only if `didPostSaveRequestSucceed()` and the post is no longer dirty. Otherwise it closes the modal, and core's error notice stays.
  - Loading uses that same `Modal`, with `isDismissible={ false }` and `__experimentalHideHeader`, showing a `Spinner` and "Loading the logged-out view…" or "Loading the logged-in view…" until the page unloads.
- [ ] **Step 4: Relax the guard and hide Import/Export in the logged-out view.** In `publish-guard.ts`, when `isLoggedOutView()`, `REQUIRED_BLOCKS` is only the Checkout entry. In `import-export.tsx`, hide the panel when `isLoggedOutView()`, so an import can't land in the meta.
- [ ] **Step 5: Verify.**
  1. `pnpm build:blocks` builds with no errors, and `composer lint -- includes/Blocks/BlockRegistry.php includes/Renderer/CheckoutLogin.php` passes.
  2. Set WooCommerce → Settings → Accounts & Privacy: guest checkout off and account creation at checkout off. In the SPA, open a duplicated layout in the editor. Expect the toggle.
  3. Switch with nothing edited. Expect a spinner, then the starter or saved view, with the toggle on "Logged out".
  4. Edit, then switch. Expect the modal. Cancel keeps the edit. Save and switch saves, shows the spinner, and lands on the other view. Reopening the first view shows the edit.
  5. With the layout active, remove the Login block in the logged-out view. Expect Save still enabled, because Email and Place order are not required there.
  6. Turn guest checkout on. Expect no toggle in the main view.
- [ ] **Step 6: Commit.** Stage the seven files above. Message: `feat(editor): switch between logged-in and logged-out layout views`

### Task 3: Render the logged-out view on the storefront and in previews

**Files:**
- Modify:
  - `includes/Renderer/CheckoutPage.php`
  - `packages/blocks/src/checkout/render.php`
  - `packages/blocks/src/login/render.php`
  - `packages/blocks/src/checkout/layout-actions.tsx` (Preview)

**Interfaces:**
- Consumes:
  - `Layout::$logged_out_content` (Task 1).
  - `CheckoutLogin::must_log_in()` (Task 2).
  - `isLoggedOutView()` and `LayoutEditorEmbed::QUERY_VIEW` / `VIEW_LOGGED_OUT` (Tasks 1 and 2).
- Produces: `CheckoutPage::is_logged_out_view(): bool`. It calls `layout()` first, so it is valid from any block render.

- [ ] **Step 1: Swap in the logged-out view in `CheckoutPage`.** Add `private bool $logged_out_view = false;` and apply the view inside `resolve()`, after the layout is found. The view applies when `'' !== $layout->logged_out_content` and either of these holds:
  - Preview requests: `$_GET[ LayoutEditorEmbed::QUERY_VIEW ] === VIEW_LOGGED_OUT`.
  - Other requests: `function_exists( 'WC' ) && CheckoutLogin::must_log_in()`.

  When it applies, set `$layout->content = $layout->logged_out_content` and set the flag.
- [ ] **Step 2: Render the merchant's view from the Checkout block.** In `checkout/render.php`, before the `must_log_in()` branch, add: when `CheckoutPage::get_instance()->is_logged_out_view()`, print `<div {$yaycheckout_wrapper}>{$content}</div>` and return. This prints the merchant's inner blocks instead of WooCommerce's stock login and message, with no `<form>`.
- [ ] **Step 3: Let the Login block render in the logged-out view.** In `login/render.php`, render when `CheckoutLogin::reminder_shown() || CheckoutPage::get_instance()->is_logged_out_view()`.
- [ ] **Step 4: Have Preview carry the view.** When `isLoggedOutView()`, append `QUERY_VIEW=logged-out` to `link.url` with `addQueryArgs`.
- [ ] **Step 5: Verify.**
  1. Run `pnpm build:blocks`, then `composer lint -- includes/Renderer/CheckoutPage.php packages/blocks/src/checkout/render.php packages/blocks/src/login/render.php`. Expect both to pass.
  2. Keep the Task 2 settings and activate the duplicated layout with its saved logged-out view. Open `?page_id=9` in a private window with a cart. Expect the logged-out view, with no `form.checkout`.
  3. Log in through its Login modal. Expect a reload into the main layout's form.
  4. As the logged-in admin, open `?page_id=9`. Expect the main layout.
  5. Turn account creation at checkout on. As a guest, expect the main layout with Create account required.
  6. Delete the meta with `wp post meta delete <ID> _yaycheckout_logged_out_content`. As a guest, expect today's output: the main layout with WooCommerce's "You must be logged in to checkout." message.
  7. In the editor's logged-out view, click Preview. Expect the logged-out view in the new tab.
- [ ] **Step 6: Commit.** Stage the four files. Message: `feat(storefront): show guests the logged-out view when login is required`

**Final check (acceptance 6):** with login required, save a logged-out view. A guest in a private window sees it, logs in through its Login block, and lands on the main checkout form. Then run `/yayplancheck docs/plans/2026-10-15-logged-out-view.md`.
