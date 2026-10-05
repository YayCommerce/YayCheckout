# Closing the layout editor discards unsaved edits without asking

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`apps/admin/src/pages/layouts/LayoutsPage.tsx` opens the block editor as an iframe of `post.php?yc-embed=1` (`LayoutEditorFrame`) inside a `Dialog` whose `onOpenChange` is `onEditorOpenChange`. The X button, a click outside and Esc all close it, with no dirty check: `useRouteLeaveGuard` guards only the Design and Settings pages. Nothing carries messages between the iframe and the SPA; there is no `postMessage` or message listener in `apps/admin/src`, `packages/blocks/src` or `includes`. Removing an iframe unloads its page without a beforeunload prompt, so Gutenberg's unsaved-changes warning never shows (inferred from the HTML spec).

`LayoutPublishGuard` in `packages/blocks/src/checkout/publish-guard.ts` calls `lockPostSaving` for drafts too, while `LayoutCoreRestController::prepare_item_for_database()` refuses only a publish or a schedule. A layout that has lost its Checkout block, or has an extra field with no Key yet, can't be saved as a draft either.

Also inferred: `onEditorOpenChange` closes with `navigate(-1)` when the page pushed the entry, but navigations inside the iframe add entries to the same session history, so that step can go back inside the iframe instead. The post-lock modal's "Take over" link, which `PostLockedModal` in WordPress core's `wp-includes/js/dist/editor.js` builds from `post.php` and the lock arguments, and the Revisions screen's "Go to editor" link, from `get_edit_post_link()`, both drop `yc-embed=1`, so full wp-admin loads inside the modal and `LayoutEditorEmbed::REST_HEADER` is no longer sent.

`LayoutEditorEmbed::print_embed_styles()` (`includes/Engine/Admin/LayoutEditorEmbed.php`) hides the chrome through internal editor classes. Its `.edit-post-layout.is-sidebar-opened` rule matches nothing: WordPress 7.1.2's editor CSS and JS no longer use that class. The embed came in `3396a34` and the guard in `5a76a11`. Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

A merchant who presses Esc or clicks outside the modal loses every change since the last autosave, and nothing says so. Gutenberg's local backup may offer the edits back on the next open, but only in that browser. Halfway through a rebuild, Save draft is disabled, so the only way to keep the work is to finish it first.

## Suggested fix

Guard the close. The iframe is same-origin, so `onEditorOpenChange` can ask the editor's `core/editor` store for `isEditedPostDirty()` through the iframe's `contentWindow`, or the embed page can post its dirty state to the parent. Then ask with `UnsavedChangesDialog` (`apps/admin/src/components/shared/UnsavedChangesDialog.tsx`). Let drafts save: refuse only a publish or schedule in the guard, for example through the editor's `editor.preSavePost` filter where the supported WordPress versions have it, and keep the notice. Confirm the inferred history and link behaviour before changing them. If confirmed, close by clearing the `edit` search param instead of `navigate(-1)`, and carry `yc-embed=1` across the editor's own links. Drop the dead `.is-sidebar-opened` rule.

## Done when

- [ ] With an unsaved edit in the embedded editor, Esc, the X button and a click outside the modal each ask before closing, and choosing to stay keeps the edit.
- [ ] In the embedded editor, a layout with no Checkout block saves as a draft, and Publish is still refused with the guard's message.
- [ ] After opening Revisions from the embedded editor and returning, one close of the modal shows the Layouts list with no `edit` parameter in the URL.
- [ ] The Take over link and the Revisions screen's Go to editor link, followed from the embedded editor, load a URL with `yc-embed=1` and show no wp-admin menu.
- [ ] `git grep -n 'is-sidebar-opened' -- includes` prints nothing.
