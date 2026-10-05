# A layout is one post: its markup in `post_content`, its visibility in `post_status`

**Purpose:** why a checkout layout is stored as a single custom post, with no second copy of its markup and no status of our own.

**Update when:** never edit it. Supersede it with a new record if layouts move out of the post, gain a published copy, or gain a status vocabulary of their own.

## Status

Accepted, 2026-10-05, by Trace Bui, who confirmed the reasons. Backfilled: the post type came in `1d5768b` (2026-09-09); the single status vocabulary in `d3d364b` (2026-09-17); dropping the published snapshot in `8c67dfa` (2026-09-18).

## Context

- Merchants edit layouts in WordPress's block editor, which saves posts through core's `wp/v2` REST API and gives autosave, revisions, scheduling and the Publish button for free.
- The first version kept a published snapshot of each layout in post meta, beside `post_content`, and a status vocabulary of its own beside `post_status`. Every path that publishes a post (the editor, cron's `publish_future_post`, quick edit, WP-CLI) then needed a hook to rebuild the snapshot, and the plugin's status and WordPress's could disagree.
- Options turned down:
  - a custom table: loses the editor's saving, revisions and scheduling;
  - a published snapshot in meta: two copies of the markup, kept in step by hooks on every publish path;
  - a status of our own: a second answer to "can shoppers see this?" that WordPress's own screens don't know about.

## Decision

- A layout is one `yay_checkout_layout` post. `post_content` is the only copy of its markup, and `post_status` the only answer to whether shoppers may see it. Publishing is a status change, the same one core makes.
- The wire `status` is `post_status` (`Enums\LayoutStatus`): `draft`, `pending`, `future`, `publish`, and `trash` for an archived layout.
- Which layouts serve the storefront is separate from status: the active set in `Layout::OPTION_ACTIVE`.
- The rules that a published layout must meet (`LayoutMarkupReader::publish_error()`) are enforced on every path that can publish: the plugin's REST routes and core's `wp/v2` through `LayoutCoreRestController`.

## Consequences

- Drafts, autosave, revisions and scheduling are core's, with no code of ours.
- Editing a published layout changes what shoppers see on the next request: there is no staged copy.
- Core's REST API answers reads of published posts without a capability check, so `LayoutCoreRestController` has to require one; and any shop manager can create a layout through `wp/v2`, so the Free limit is enforced on core's insert too (`Engine\LayoutCapGuard`).
- WordPress's own behaviours apply to layouts and need guards: trash purging (`Engine\LayoutTrashSync`), and trashing or unpublishing the active layout (`Engine\LayoutActiveGuard`).
