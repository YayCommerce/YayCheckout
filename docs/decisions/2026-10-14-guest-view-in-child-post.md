# The blocked-guest design is a post of its own, a child of the layout, edited in core's editor

**Purpose:** why what a guest sees when WooCommerce gives them no checkout form is stored as a `yay_checkout_guest` post whose `post_parent` is the layout, and edited in the stock block editor, and not as a block in the layout's `post_content` or as a meta of the layout edited in a nested editor.

**Update when:** never edit it. Supersede it with a new record if the guest design moves out of its post, if a layout gains more than two views, or if the view shows under conditions other than `CheckoutLogin::must_log_in()`.

## Status

Accepted, 2026-10-15, by Alan, who chose this option after the meta version had shipped to review.

## Context

- When guest checkout is off and account creation during checkout is off, WooCommerce prints no checkout form for a guest (`CheckoutLogin::must_log_in()` mirrors that). The Checkout block then printed the Login block and one plain sentence, with no way for the merchant to design it.
- The guest view is a heading, some text, an image, a login prompt and a link back to the cart. It holds none of the checkout's blocks.
- A layout is one post: `post_content` is the checkout markup and `Layout::META_DESIGN` its design ([layout is one post](2026-10-05-layout-is-one-post.md)). The guest view is a second set of blocks, which a single post's editor cannot show beside the first without custom editor code.
- Options turned down:
  - a `yaycheckout/guest-view` block inside Checkout, in `post_content`: built and working, but it put a view that needs no checkout blocks into the checkout markup;
  - a meta of the layout (`_yaycheckout_guest_view`, `revisions_enabled`) edited in a `BlockEditorProvider` nested inside the Checkout block: built and working, but every part of the editor (toolbars, List View, the block inspector, inserters, autosave) had to be re-wired by hand, through `__experimental` and `__unstable` APIs, class names and CSS copied into the canvas iframe. It kept showing visual bugs and would break with WordPress updates;
  - the guest post in the layout's own post type: every query of layouts (list, Free's limit, assignment, active set, trash sync) would need to exclude it;
  - a second root block beside Checkout, swapping the editor's blocks between two sets, a separate layout assigned to `login_status = guest`, and showing the view whenever guest checkout is off: as in the spec's alternatives.

## Decision

- The guest view is the `post_content` of a `Layout::GUEST_VIEW_POST_TYPE` (`yay_checkout_guest`) post, one per layout, found by `post_parent`. It is always published: on a draft, the editor's autosave overwrites the post itself, which would put unsaved edits on the storefront; on a published post it goes to an autosave revision. It shares the layout's REST controller, `LayoutCoreRestController`, which keeps reads behind the capability and applies the layout publish rule to layouts only.
- `LayoutPostType::sanitize_guest_view()`, on `wp_insert_post_data`, keeps only `Layout::GUEST_VIEW_BLOCKS` on every write: the editor, a revision restore, autosave and `LayoutRepository`. `BlockRegistry::allowed_blocks()` offers only those blocks in its editor.
- `LayoutRepository` owns it: `guest_view_id()`, `guest_view()`, `save_guest_view()`. Create and Reset write the default (`templates/layouts/guest-view.php`), duplicate and Pro's import copy it, and `delete()` deletes it, since core deletes no child of a non-hierarchical post. Opening a layout in the editor gives it the default guest view when it has none.
- The editor header's "Checkout form | Guest view" switch opens the other post in the same frame (`post.php?post=<id>&action=edit&yc-embed=1`). With unsaved changes it asks to save first. The guest view's editor shows the layout's design and the same header actions, without Activate and Deactivate; its Preview opens the layout's preview link with `yaycheckout_view=guest`.
- `checkout/render.php` prints it, in a `<div>` and not the `<form>`, when `must_log_in()` is true and it is not empty; `CheckoutLogin::render_guest_view()` lets a Login inside it print whatever the login reminder option says.

## Consequences

- No custom editor code: toolbars, List View, the inspector, inserters, undo, autosave and revisions are core's, so WordPress updates reach the guest view as they reach any post.
- The layout and its guest view save separately: a Save in one never writes the other, and switching asks before leaving unsaved changes.
- Every path that copies or removes a layout has to handle its guest view post too; today those are `LayoutRepository::insert_draft()`, `reset()`, `delete()` and `uninstall.php`.
- In its editor the guest view shows without the layout's header and the Checkout block's width; it uses the theme's content width.
- The view shows in one WooCommerce configuration only (guest checkout off and "During checkout" off), so a merchant can design it and never see it on the live site unless they set that configuration.
