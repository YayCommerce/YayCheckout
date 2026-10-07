# A private layout, or one started at post-new.php, escapes the layout list and the Free limit

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

- **Private in the editor.** WordPress core's editor offers Private in its Status panel (`wp-includes/js/dist/editor.js` `STATUS_OPTIONS`), and nothing in `Engine\Admin\LayoutEditorEmbed` or the blocks hides it. `LayoutStatus::listed()` and `LayoutStatus::all()` leave out `private`, so a private layout is in neither the Layouts tab nor the Archived tab, and `LayoutQuery::of_statuses()` refuses to list it. If it was active, `LayoutActiveGuard::deactivate_when_unpublished()` drops it from the active set.
- **Private over REST.** `LayoutRepository::count()` counts only `LayoutQuery::listed()`, so a private layout frees a Free slot. `LayoutCapGuard::enforce_limit()` uses that count, so on Free `POST /wp/v2/yay_checkout_layout` with `status: private` succeeds however many private layouts exist. `LayoutRepository::activate()` then publishes any of them through `publish()`, with no limit check.
- **post-new.php.** `wp-admin/post-new.php?post_type=yay_checkout_layout` makes an auto-draft with WordPress core's `wp-admin/includes/post.php` `get_default_post_to_edit()`, outside REST. The editor's first save is an update with an ID, and `LayoutCapGuard::enforce_limit()` skips updates, so the layout lands past the limit.
- `LayoutRepository::restore()` also skips the limit, on purpose; that stays.

`LayoutCapGuard` came in `827b09f`.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

A merchant who picks Private loses the layout from the app; only `wp-admin/edit.php` or REST still shows it. If it was active, shoppers stop seeing it with no warning; on Free they get WooCommerce's checkout. The three-layout Free limit can be passed through either path.

## Suggested fix

Give layouts no private status: in a `wp_insert_post_data` filter, turn a layout's `private` into `draft`. That also covers Quick Edit's Private box and REST. Have `LayoutCapGuard::enforce_limit()` treat an update of a stored `auto-draft` as an insert. Have `activate()` check the limit when `count()` doesn't include the layout yet, for layouts already stored as private. Leave `restore()` as it is.

## Done when

- [x] Choosing Private in the embedded editor's Status panel and saving leaves the layout in the Layouts tab. — `LayoutWriteGuard::keep_layout_rules()` turns `private` into `draft` (checked in the embedded editor)
- [x] On Free, no series of `POST /wp/v2/yay_checkout_layout` requests, `status: private` included, leaves more than `LayoutRepository::FREE_LIMIT` layouts that aren't archived. — private saves are stored as draft, so `LayoutCapGuard::enforce_limit()`'s `count()` includes them (REST create at the limit answers 403)
- [x] On Free with three layouts in the Layouts tab, saving a new layout started at `wp-admin/post-new.php?post_type=yay_checkout_layout` is refused, and the tab still shows three. — `LayoutCapGuard::enforce_limit()` treats the first save of an `auto-draft` as an insert (403 at the limit)
- [x] On Free at the limit, `LayoutRepository::activate()` refuses a layout that `LayoutRepository::count()` doesn't include. — `LayoutRepository::activate()` checks `Plan::layout_limit_reached()` for a status outside `LayoutStatus::listed()`
