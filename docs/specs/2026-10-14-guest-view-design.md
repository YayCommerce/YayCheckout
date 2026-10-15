# Guest View Design

**Status:** accepted 2026-10-15
**Related:** Third version. The first (a Guest view block inside the layout's `post_content`) and the second (a layout meta edited in a nested block editor) were built and never committed; the merchant chose a child post edited in core's editor, because the nested editor kept showing visual bugs and leaned on unstable editor APIs.

## Goal

A merchant can design what a guest sees on the checkout page when WooCommerce gives guests no checkout form, in the same layout editor, with a switch between the checkout form and that guest view.

## Context

- **Today.** `CheckoutLogin::must_log_in()` (`includes/Renderer/CheckoutLogin.php`) is true when the shopper is logged out, "During checkout" account creation is off (`woocommerce_enable_signup_and_login_from_checkout`) and registration is required (guest checkout off, `woocommerce_enable_guest_checkout`). Then `packages/blocks/src/checkout/render.php` drops the form and every inner block, prints the Login block when `CheckoutLogin::reminder_shown()` (option `woocommerce_enable_checkout_login_reminder`) and the plain text "You must be logged in to checkout." Blocks outside the Checkout block (logo, wrappers) still print.
- **The guest view has no form blocks.** It is for a heading, text, an image, a login prompt and a link back to the cart, so it needs none of the checkout blocks, and a layout's checkout markup does not need to hold it.
- **Layout storage.** A layout is one `yay_checkout_layout` post: `post_content` is the checkout markup, and the design is the meta `Layout::META_DESIGN` (`_yaycheckout_design`), registered with `register_post_meta` in `PostTypes\LayoutPostType` with `show_in_rest`, so the editor's Save writes it with the markup. The post type supports `title, editor, revisions, custom-fields, author, thumbnail`. The plugin requires WordPress 6.5 (`yaycheckout.php`).
- **Where a layout is copied or removed**, which the guest view post follows: `LayoutRepository::insert_draft()` (create, duplicate, import), `reset()`, `delete()`, `uninstall.php`, and Pro's export (`Pro\Controllers\LayoutExportRestController`: `layouts[0]`) and import (`Pro\Services\LayoutImportReview::layout()`).
- **Templates.** `templates/layouts/<slug>/markup.php` and `design.json`, read by `Controllers\LayoutTemplateCatalogue` (`markup()`, `design()`).
- **Editor.** The SPA opens `post.php?post=<id>&action=edit&yc-embed=1` in an iframe (`Engine\Admin\LayoutEditorEmbed`); `packages/blocks/src/checkout/layout-actions.tsx` puts Preview, Save and Activate in the header. `BlockRegistry::allowed_blocks()` decides each editor's blocks by post type, and `Engine\Admin\LayoutEditorCanvas` gives the layout's canvas its design.
- **Preview.** `Renderer\PreviewRequest` reads `?yaycheckout_preview=<token>` (`Helpers\PreviewToken::QUERY_ARG`) and makes the request render that layout with a sample cart and ordering disabled.
- **Site state seen while designing this.** With Safe mode on (`yaycheckout_settings.general.safe_mode`), `CheckoutPage` stands down and the theme's checkout shows. The guest view, like the rest of the layout, shows only with Safe mode off.

## Scope

**In:**
- A post type `Layout::GUEST_VIEW_POST_TYPE` (`yay_checkout_guest`): one published post per layout, found by `post_parent`, whose `post_content` is the guest view. Revisions on; every write keeps only `Layout::GUEST_VIEW_BLOCKS`.
- Rendering: when `must_log_in()` is true and the guest view is not empty, Checkout prints its blocks in a `<div>`, not the `<form>`.
- A "Checkout form | Guest view" switch in the editor header that opens the other post in the same frame, asking to save unsaved changes first.
- The guest view's editor: core's block editor with only the allowed blocks, the layout's design, an info notice, and the header's Preview and Save.
- Preview from the guest view's editor shows the guest view to the logged-in merchant.
- Login inside the guest view always prints for a logged-out shopper and for a preview of the guest view.
- A default guest view, shared by the three templates: written when a layout is created from a template, when it is reset, and when a layout without one is opened in the editor.
- Duplicate copies it, delete and uninstall remove it; Pro's export carries it and its import reads it.
- Free and Pro alike.

**Out:**
- Any change to what a guest sees when "During checkout" is on or guest checkout is on: they get the form as today.
- A different guest view per condition, per role or per product.
- Checkout blocks in the guest view: Billing, Shipping, Email, Field, Payment, Place order, Steps, Coupon form, Express pay and Order Summary.
- The layout's header and the Checkout block's width in the guest view's editor: it uses the theme's content width.
- Activate and Deactivate from the guest view's editor.
- A migration: the feature is unreleased.
- A new publish rule: a layout without a guest view publishes as before.
- Free's import (Free has no import or export).

## Acceptance criteria

1. **Blocked guest, layout has a guest view:** given guest checkout off and "During checkout" off, a logged-out shopper on `/checkout/` (Safe mode off, layout active, guest view not empty) gets HTML containing `yaycheckout-checkout--guest` and the guest view's content, and no `<form name="checkout"`.
2. **Blocked guest, no guest view:** the same request on a layout with no guest view post, or an empty one, prints "You must be logged in to checkout." and the Login block as today.
3. **Not blocked:** with guest checkout on, or with "During checkout" on, the page prints the stock form and the guest view's content appears nowhere in the HTML.
4. **Logged in:** a logged-in shopper never sees the guest view, whatever the options say.
5. **Login always shows:** with `woocommerce_enable_checkout_login_reminder` = `no`, a Login block in the guest view still prints for the blocked guest; a Login block in the checkout markup still prints nothing, as today.
6. **Sanitizer:** writing a guest view (`LayoutRepository::save_guest_view()` or `POST /wp/v2/yay_checkout_guest/<id>`) holding a `yaycheckout/field` block, a `core/html` block or a `<script>` tag stores it without them. Allowed block names are exactly `core/heading`, `core/paragraph`, `core/image`, `core/group`, `core/columns`, `core/column`, `core/buttons`, `core/button`, `core/separator`, `core/spacer`, `yaycheckout/login`, `yaycheckout/return-to-cart`, kept in `Layout::GUEST_VIEW_BLOCKS`; the guest view's editor offers only these.
7. **Capability:** an anonymous `GET /wp/v2/yay_checkout_guest/<id>` and `GET /wp/v2/yay_checkout_guest` answer 401.
8. **Switch:** the header of a layout's editor and of its guest view's editor shows "Checkout form" and "Guest view", the current one pressed. Clicking the other opens it in the same frame with `yc-embed=1`. With unsaved changes, a "Save your changes first?" modal offers Cancel and "Save and switch".
9. **Separate saves:** saving the guest view never changes the layout's `post_content`, and saving the layout never changes the guest view.
10. **Lazy default:** opening in the editor a layout that has no guest view post creates one holding the default guest view.
11. **Notice:** the guest view's editor shows "Shown to guests only when guest checkout and account creation during checkout are both off in WooCommerce."
12. **Preview:** Preview in the guest view's editor opens the layout's preview link plus `&yaycheckout_view=guest`, and the page shows the guest view, with its Login and the banner's "Showing the Guest view.", to the logged-in merchant. Preview in the layout's editor has no `yaycheckout_view`. A request with `yaycheckout_view=guest` and no valid preview token behaves as a normal request.
13. **Templates:** a layout created from, or reset to, each of the three templates has a guest view post holding a heading "Log in to continue", one sentence, a Login block and a Return to cart block, and its `post_content` holds no copy of that content.
14. **Revisions:** editing and saving the guest view twice leaves an earlier revision of the guest view post; Reset leaves the pre-reset value in a revision.
15. **Duplicate, delete, export, import:** a duplicated layout has its own guest view post with the same content. Deleting a layout deletes its guest view post. A Pro export's `layouts[0]` has a `guest_view` string equal to it, and importing that file creates a layout whose guest view equals it, after the sanitizer. An export file without `guest_view` imports with no guest view post.
16. **Layouts list unaffected:** guest view posts appear nowhere in the layouts list and count nowhere toward Free's layout limit.
17. **End-to-end verification:**
    1. Safe mode off; WooCommerce: guest checkout off, "During checkout" off, "Enable log-in during checkout" off.
    2. Create a layout from `classic-two-column`, activate it, open it in the editor and click "Guest view".
    3. Change the heading to "Members only", Save, and click Preview.
    4. Expected: the preview shows the logo, then "Members only", the sentence, the Login block and Return to cart, and no form.
    5. In a private window open `/checkout/` with a product in the cart. Expected: the same content and no `<form name="checkout"` in the page source. Repeat at 390px and 1280px wide.
    6. Switch "Enable guest checkout" on and reload the private window. Expected: the normal form and no "Members only" anywhere in the HTML.

## Constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit generated output (`assets/dist/`); build blocks with `pnpm build:blocks`.
- Free and Pro both ship it. Nothing in Free may reference `YayCheckout\Pro`; only the Pro export and import files change for criterion 15.
- Storage is one post type, `yay_checkout_guest` (`Layout::GUEST_VIEW_POST_TYPE`), not hierarchical, linked by `post_parent`. No option, table, meta or new REST route. `Layout::to_array()` and the SPA's layout wire schema do not change.
- Editor code uses only stable WordPress APIs: no nested `BlockEditorProvider`, no `__experimental` or `__unstable` component.
- Storefront changes stand down when `CheckoutPage::get_instance()->layout()` is null.
- The preview argument is the constant `yaycheckout_view` with the one value `guest`, read only when `PreviewRequest` holds a valid token.
- A TypeScript copy of PHP names its owner: the argument name (owner `PreviewRequest::VIEW_ARG`), no other. The ids, editor URLs (with `LayoutEditorEmbed::QUERY_FLAG`), the guest post type and the allowed blocks reach the editor from PHP.
- New strings are translatable with the `yaycheckout` text domain.
- Verified in the editor and on the storefront at 390px and 1280px wide.
- Branch `alan/feat/guest-checkout-layout` from `dev/edd`, Conventional Commits, a PR into `dev/edd`.

## Approach

**Chosen:** the guest view is a post of its own, a child of the layout, edited in core's block editor; the header switch moves between the two posts.

**Architecture:**
- The guest view is not part of the checkout markup, so nothing that reads `post_content` changes.
- The server renders the guest view post's `post_content` with `do_blocks()` in `checkout/render.php`'s `must_log_in()` branch, behind a flag that lets Login print.
- Each view is a whole post in the stock editor, so toolbars, List View, the inspector, inserters, undo, autosave and revisions are core's, with no editor code of ours.

**Components:**
- `includes/Models/Layout.php` — `GUEST_VIEW_POST_TYPE = 'yay_checkout_guest'`, `GUEST_VIEW_BLOCKS` (the 12 names of criterion 6).
- `includes/Services/LayoutMarkupReader.php` — `sanitize_guest_view( string $markup ): string`: drop any block not in `GUEST_VIEW_BLOCKS` (nested ones too), serialize, `wp_kses_post`; `''` for blank markup.
- `includes/PostTypes/LayoutPostType.php` — registers the guest post type (`show_ui`, `show_in_rest`, `LayoutCoreRestController`, supports `title, editor, revisions`, the layout's capabilities) and `sanitize_guest_view()` on `wp_insert_post_data`.
- `includes/Repositories/LayoutRepository.php` — `guest_view_id( int $layout_id ): int`, `guest_view( int $layout_id ): string`, `save_guest_view( int $layout_id, string $markup ): int` (creates a published post titled "Guest view" or updates, with a revision; published so autosave goes to an autosave revision, not the live guest view); `insert_draft()` writes it, `reset()` writes the default, `duplicate()` copies it, `delete()` deletes it.
- `includes/Controllers/LayoutTemplateCatalogue.php` — `guest_view(): string`, the output of `templates/layouts/guest-view.php`.
- `includes/Blocks/BlockRegistry.php` — `editor()` returns `'guest'` for the guest post type; `allowed_blocks()` returns `GUEST_VIEW_BLOCKS` there; the YayCheckout category shows there; `editor_design_data()` prints `window.yayCheckoutBlocks.guestView = { layoutId, guestId, layoutUrl, guestUrl }` in either editor, creating the default guest view for a layout that has none; the login block's data gains `guestPostType`.
- `includes/Engine/Admin/LayoutEditorCanvas.php` — the guest view's editor gets the base styles and its layout's design tokens, the title hidden and core's publish controls hidden; not the layout's full-width rules.
- `includes/Renderer/CheckoutLogin.php` — `render_guest_view()`, `in_guest_view()`, and `must_log_in()` true for a guest-view preview.
- `includes/Renderer/PreviewRequest.php` — `VIEW_ARG`, `guest_view()`, the banner suffix.
- `packages/blocks/src/checkout/render.php` — the guest branch, reading `LayoutRepository::guest_view()`.
- `packages/blocks/src/login/{render.php,edit.tsx,editor-data.ts}` — prints in a guest view whatever the reminder option; the editor drops the reminder warning in the guest view's editor.
- `packages/blocks/src/checkout/layout-view.tsx` — `GUEST_VIEW_ARG`, `useIsGuestView()`, `ViewSwitch` (switch, save modal, notice).
- `packages/blocks/src/checkout/layout-actions.tsx` — mounts in both editors; in the guest view's: the switch, Preview of the parent layout with the argument, and Save.
- `includes/Pro/...` — export adds `guest_view`; import reads it and passes it to `LayoutRepository::import()`.
- `uninstall.php` — deletes guest view posts with the layouts.

**Edge cases and failure modes:**
- **No guest view post, blocked guest:** today's plain text.
- **A disallowed block written by any path** (code editor, REST, import, revision restore): stripped by the `wp_insert_post_data` filter.
- **Save while switching fails:** the modal closes and the editor stays on the current post.
- **Layout deleted:** its guest view post goes too; an archived layout keeps it.
- **Guest view post opened directly:** its editor finds its layout through `post_parent` for the design, the switch and Preview.
- **Preview token expired:** the existing 410 applies.
- **Pro import of an old file:** no `guest_view` key means no guest view post until the layout is opened in the editor.

**Decision record:** `docs/decisions/2026-10-14-guest-view-in-child-post.md`.

## Alternatives considered

| Option | Why rejected |
|---|---|
| A `yaycheckout/guest-view` block inside Checkout, in `post_content` | Built first and working, but put a view that needs no checkout blocks into the checkout markup. |
| A layout meta edited in a nested `BlockEditorProvider` | Built second and working, but every part of the editor had to be re-wired by hand through unstable APIs and copied CSS; kept showing visual bugs and would break with WordPress updates. |
| The guest post in the layout's own post type | Every query of layouts would need to exclude it. |
| A second root block beside Checkout | Breaks the "Checkout is the one root" assumption in the inserter root, `LayoutMarkupReader::has_checkout()`, templates and import. |
| Swap the editor's blocks between two sets | A Save in Guest view mode would write the guest blocks into `post_content`. |
| A separate layout assigned to `login_status = guest` | Pro only, two designs to keep alike, and serves guests even when guest checkout is on. |
| Guest view whenever guest checkout is off | With account creation during checkout on, WooCommerce still gives guests a form. |

## Rollout

Release note: merchants can design what blocked guests see (Layout editor > "Guest view"). Every layout gets the default guest view when created, reset or opened in the editor. No setting, no migration.

## Open questions

None.
