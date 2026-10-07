# Core's delete, Quick Edit, Bulk Edit and revision restore skip the layout rules

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

- **Delete.** `LayoutRepository::delete()` refuses a layout that isn't archived, but only the plugin's route calls it. `DELETE /wp/v2/yay_checkout_layout/<id>?force=true` runs WordPress core's `wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php` `WP_REST_Posts_Controller::delete_item()`, which calls `wp_delete_post( $id, true )`. `LayoutCoreRestController::delete_item_permissions_check()` refuses only inside the embedded editor, and `LayoutActiveGuard` only an active layout, so any other layout is deleted outright. This is reachable over the API only.
- **Quick and Bulk Edit.** `LayoutCoreRestController::prepare_item_for_database()` holds the publish rules for wp/v2 only. WordPress core's Quick Edit (`wp-admin/includes/ajax-actions.php` `wp_ajax_inline_save()`, through `edit_post()`) and Bulk Edit (`wp-admin/includes/post.php` `bulk_edit_posts()`) call `wp_update_post()`, so they publish a layout that `LayoutMarkupReader::publish_error()` refuses. Both are on `wp-admin/edit.php?post_type=yay_checkout_layout`. It has no menu item, but a direct URL opens it, and so does the post-lock dialog's "Exit editor" link. The Revisions screen returns to a `wp-admin/post.php` without `yc-embed`, whose back button leads there (inferred). The harm is bounded: `LayoutRepository::activate()` checks the rules again, as its comment says.
- **Revision restore.** WordPress core's `wp-includes/revision.php` `wp_restore_post_revision()`, run from `wp-admin/revision.php`, rewrites `post_content` through `wp_update_post()`. On an active, published layout it can put back markup that `publish_error()` refuses, with no check (inferred).

The accepted record `docs/decisions/2026-10-05-layout-is-one-post.md` says the publish rules hold on every path that can publish. These paths don't hold them yet. The record isn't edited; the fix makes it true. The rules reached wp/v2 in `7819e35`.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

A REST client or script can permanently delete a layout that the app would only archive. Quick and Bulk Edit can't make a broken layout serve, but they break the record's rule. A revision restore can put markup that fails the publish rules in front of shoppers (inferred).

## Suggested fix

Guard core's choke points, not each entry point. A `pre_delete_post` filter refuses to delete a layout that isn't archived; `docs/backlog/2026-10-05-archive-deletes-a-layout-when-the-trash-is-off.md` needs the same filter. A `wp_insert_post_data` filter keeps a layout's stored status or content when the save would leave it `publish` or `future` with markup that `publish_error()` refuses. That covers Quick Edit, Bulk Edit, revision restore and WP-CLI at once; `prepare_item_for_database()` keeps its clearer 400 for the editor.

The new filter enforces `LayoutMarkupReader::publish_error()`, so an integration test is warranted there under `CLAUDE.md`'s Tests rule. If the team decides a path may skip the rules, supersede the record instead.

## Done when

- [x] `DELETE /wp/v2/yay_checkout_layout/<id>?force=true` on a layout that isn't archived answers a 4xx, and the layout still exists. — `LayoutCoreRestController::delete_item_permissions_check()` answers 409 via `LayoutStatus::deletable()`; `LayoutWriteGuard::refuse_deleting_unarchived()` on `pre_delete_post` (checked on yaycheckout.test)
- [x] Quick Edit, setting a draft with no Checkout block to Published, leaves it unpublished. — `LayoutWriteGuard::keep_layout_rules()` on `wp_insert_post_data` (checked in edit.php Quick Edit)
- [x] Bulk Edit, setting a draft with no Checkout block to Published, leaves it unpublished. — `bulk_edit_posts()` → `wp_update_post()` → `LayoutWriteGuard::keep_layout_rules()`
- [x] Restoring, from `wp-admin/revision.php`, a revision that `LayoutMarkupReader::publish_error()` refuses onto a published layout doesn't leave it published with that markup. — `wp_restore_post_revision()` → `wp_update_post()` → `LayoutWriteGuard::keep_layout_rules()` keeps the live markup (checked with WP-CLI)
