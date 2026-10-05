# Archiving a layout deletes it for good when the site's trash is off

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`LayoutRepository::archive()` (`includes/Repositories/LayoutRepository.php`) deactivates the layout, then calls `wp_trash_post()`. When `wp-config.php` defines `EMPTY_TRASH_DAYS` as 0, WordPress core's `wp-includes/post.php` `wp_trash_post()` calls `wp_delete_post( $id, true )` instead. The layout is no longer active, so `LayoutActiveGuard::refuse_for_active_layout()` lets `pre_delete_post` through, and core deletes the post with its meta and revisions.

`wp_delete_post()` returns the deleted `WP_Post`, so the `instanceof` check in `archive()` passes and the route answers with an archived layout. `trashed_post` never fires, so `LayoutTrashSync::keep_trashed_layout()` never runs, though its docblock says "Archived means kept, on every path". The `LayoutStatus::ARCHIVED` docblock (`includes/Enums/LayoutStatus.php`) still says cron empties the archive after `EMPTY_TRASH_DAYS`, which `LayoutTrashSync` stopped. Archive came in `a03eb67`.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

`apps/admin/src/pages/layouts/ArchiveModal.tsx` tells the merchant "Archiving is not deleting", and the app reports success. The layout then vanishes from both tabs, with its revisions and no undo. `EMPTY_TRASH_DAYS` lives in `wp-config.php`, out of the merchant's sight, and nothing in the app says it changes what Archive does.

## Suggested fix

Don't hand an archive to `wp_trash_post()` when core has no trash. When `EMPTY_TRASH_DAYS` is 0, have `archive()` set `post_status` to `trash` itself. Store `_wp_trash_meta_status`, so `wp_untrash_post()` can restore it, and no `_wp_trash_meta_time`, so `wp_scheduled_delete()` never purges it.

Also refuse, in a `pre_delete_post` filter, to delete a layout that isn't archived, so no path turns Archive into Delete. `docs/backlog/2026-10-05-core-write-paths-skip-the-layout-rules.md` needs the same filter for wp/v2. Then correct the `LayoutStatus::ARCHIVED` docblock.

## Done when

- [ ] With `define( 'EMPTY_TRASH_DAYS', 0 );` in `wp-config.php`, archiving a layout from the Layouts page moves it to the Archived tab.
- [ ] With the trash off, that layout restores from the Archived tab with its content and revisions.
- [ ] With the trash off, an archived layout is still there after core's `wp_scheduled_delete` cron event runs.
- [ ] The `LayoutStatus::ARCHIVED` docblock agrees with `LayoutTrashSync`: archived layouts are never purged.
