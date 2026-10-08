# Layout Active / Inactive Status Design

**Status:** accepted 2026-10-08
**Related:** docs/decisions/2026-10-05-layout-is-one-post.md

## Goal

Merchants see one state per layout: **Active** or **Inactive** (plus **Archived** in the Archive tab). They never see WordPress's Published, Draft, Pending review or Scheduled. Activate publishes the layout, and Deactivate turns it back into a draft. The embedded editor stops offering WordPress's Publish, Update and Switch to draft. Instead it offers **Save**, plus **Activate** or **Deactivate**.

## Context

- A layout's status is its `post_status` (`includes/Enums/LayoutStatus.php`). Which layout is live is a separate ordered set stored in the `yaycheckout_active_layout` option (`LayoutRepository::active_ids()`, `set_active()`).
- `LayoutRepository::activate()` already publishes a layout before activating it. `deactivate()` (`includes/Repositories/LayoutRepository.php:221`) removes the layout from the set but leaves it published. That creates a third state, "published but inactive", which is where the confusion comes from.
- `LayoutActiveGuard::deactivate_when_unpublished()` (`includes/Engine/LayoutActiveGuard.php:31`) deactivates a layout when it leaves publish or future. It calls `LayoutRepository::deactivate()`.
- In the SPA, `LayoutStatusBadge.tsx` shows the post status. `LayoutsPage.tsx:564-566` has separate Status, Active and Published columns. `SetActiveModal.tsx` reads "Publish & activate", and `DeactivateModal.tsx:40` says the layout "stays published".
- The editor is WordPress core's (`LayoutEditorEmbed.php`, embedded in an iframe). Its Publish, Update and Save draft buttons, the sidebar Status row and Switch to draft are all core and nothing overrides them. `LayoutEditorCanvas.php` only hides the document bar and the schedule row.
- `packages/blocks/src/checkout/publish-guard.ts` locks every manual save, drafts included, while `publish_error()` objects. Editor data reaches the block scripts through `packages/blocks/src/shared/editor-data`.

## Scope

**In:**
- **Status shown everywhere:** the SPA layouts list, `LayoutSelect`, `AssignmentDrawer` and the storefront preview banner show Active or Inactive, decided by membership in the active set (`is_active`). Archived layouts show Archived.
- **Deactivate:** the repository sets a published, pending or scheduled layout to `draft` after removing it from the active set.
- **Free replacement:** when activating B replaces the active layout A, A goes through the same deactivation and becomes `draft`.
- **Editor buttons:**
  - Core's header Publish, Update and Save draft buttons, the sidebar Status/visibility row and Switch to draft are hidden.
  - A custom **Save** button keeps the current status.
  - An **Activate** button appears when the layout is inactive, and a **Deactivate** button when it is active. Each has a confirm modal with the same copy as the SPA modals.
- **Publish guard:** `publish-guard.ts` locks saving only when the layout is active, or its status is publish or future. Inactive drafts save freely. The Activate button stays disabled while the guard reports a problem.
- **Wording:** the merchant-facing text in `publish_error()` and its JS mirror says "activate" instead of "publish".
- **Docs:** `docs/architecture.md` (lines 43-55, 83-84) is updated, and a new decision record is added (see Approach).

**Out:**
- **No migration.** Existing layouts that are published but not active, pending or scheduled keep their `post_status` and display as Inactive.
- Pro→Free downgrade cleanup of extra published layouts.
- Staged edits: saving an active layout still changes the live checkout on the next request, as it does today.
- Quick Edit and Bulk Edit on `edit.php` (no menu entry links to them).
- Renaming layouts (backlog item `2026-10-07-rename-layout-in-spa.md`).
- Removing `LayoutStatus` values or the wire `status` field. They stay as internal and REST detail.

## Acceptance criteria

1. **List:** the layouts list shows one "Status" column with "Active" or "Inactive". The "Active" and "Published" columns are gone, and no layout UI in the SPA shows "Published", "Draft", "Pending review" or "Scheduled".
2. **Deactivate:** given an active layout, when the merchant confirms Deactivate in the SPA, then the layout leaves `yaycheckout_active_layout` and its `post_status` is `draft`.
3. **Free replacement:** on Free, given layout A is active, when B is activated, then A's `post_status` is `draft` and the option holds only B's id.
4. **Pro:** given layouts A and B are active, when B is deactivated, then A stays active and `publish`, and B is `draft`.
5. **Editor, inactive layout:** the header shows "Save" and "Activate" and no "Publish" or "Save draft". Save leaves the status `draft`. Activate (after confirming) saves any unsaved edits, then calls `POST yaycheckout/v1/layouts/{id}/activate`. The layout ends `publish` and active, and the header switches to "Save" + "Deactivate".
6. **Editor, after Activate:** saving again in the same editor session leaves the layout active and `publish`. The editor's local status must be refreshed after the REST call.
7. **Editor, active layout:** Deactivate (after confirming) calls `POST yaycheckout/v1/layouts/{id}/deactivate`. The layout ends `draft` and inactive, and the header shows "Save" + "Activate".
8. **No core status controls:** the sidebar shows no Status/visibility row and no "Switch to draft" for a layout.
9. **Invalid drafts:** an inactive layout with no Checkout block saves without a lock. Activate is disabled and the guard notice reads "Add a Checkout block to this layout before activating it."
10. **Invalid active layout:** with the Checkout block removed from an active layout, Save is locked with the same notice. The server also refuses with 400 `yaycheckout_layout_unpublishable`.
11. **Legacy layouts:** a layout that is published but not active shows as "Inactive", and Activate works on it.
12. **Preview banner:** it shows "Active", "Inactive" or "Archived" in place of the post-status label.
13. **End-to-end:**
    - Create a layout in the SPA, open the editor, and Save. The list shows Inactive.
    - Activate in the editor and close it. The list shows Active, and the storefront checkout renders the layout.
    - Deactivate from the list. The list shows Inactive, `wp post get <id> --field=post_status` prints `draft`, and the storefront shows WooCommerce's checkout.

## Constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, `?->`, named arguments or union types (CLAUDE.md rule 4).
- The Free/Pro boundary is unchanged. The editor buttons ship to both builds, and Free's single-active behaviour comes from the existing `set_active()`.
- The editor header must work on the WordPress versions the plugin supports. Mount into `.editor-header__settings`, falling back to `.edit-post-header__settings` (before 6.5). This follows the pattern `LayoutEditorCanvas` already uses for old class names.
- `publish_error()` stays the single rule set. `publish-guard.ts` is still its mirror (`docs/conventions.md:23`).
- No new tests unless the user accepts one through AskUserQuestion (CLAUDE.md "Tests"). Verify with the manual checks above.

## Approach

**Chosen:** derive Active/Inactive from the active set, make deactivation set the status to draft, and replace core's publish controls with a YayCheckout editor plugin.

**Architecture:**
- **Status shown:** what the merchant sees comes from `is_active`, not `post_status`. `post_status` remains the storefront's safety net, so an active layout is always published.
- **Deactivate:** `deactivate()` becomes "stop serving and unpublish". Every deactivation path (SPA, editor, Free replacement, archive) goes through it.
- **Editor:** the editor gets its own header buttons, which call the existing `activate` and `deactivate` REST routes, so the repository rules apply unchanged.

**Components:**
- `includes/Repositories/LayoutRepository.php`
  - `deactivate()`: after `store_ids()`, if `post_status` is `publish`, `future` or `pending`, call `wp_update_post( [ 'ID' => $id, 'post_status' => 'draft' ] )`. Update the docblock.
  - Add a private `remove_active( int $id )` that only edits the option.
  - `set_active()` on Free calls `deactivate()` for each replaced id.
- `includes/Engine/LayoutActiveGuard.php`: `deactivate_when_unpublished()` calls `remove_active()` instead of `deactivate()`. Otherwise the status change fires `transition_post_status` again and recurses (make `remove_active()` public, or add a public `forget_active()`).
- `includes/Services/LayoutMarkupReader.php`: reword the `publish_error()` messages from "publishing" to "activating".
- `includes/Renderer/PreviewRequest.php`: the banner label comes from the active set and archived status, not `LayoutStatus::label()`.
- `includes/Engine/Admin/LayoutEditorCanvas.php`: extend `EDITOR_CSS` to hide:
  - the header's `.editor-post-publish-button__button`, `.editor-post-publish-panel__toggle`, `.editor-post-save-draft` and `.editor-post-saved-state`;
  - the sidebar's status row (`.editor-post-status` and its 6.5+ equivalent);
  - `.editor-post-switch-to-draft`.
  Also add `isActive` to the editor data the block scripts read (`packages/blocks/src/shared/editor-data`'s PHP source).
- `packages/blocks/src/checkout/layout-actions.tsx` (new, registered as a plugin like `publish-guard.ts`): renders Save, and Activate or Deactivate, through a portal into the header settings area.
  - **Save** calls `dispatch(editorStore).savePost()`.
  - **Activate:** save if dirty, then `apiFetch` to the REST route, then refetch `wp/v2/yay_checkout_layout/{id}?context=edit`. Push the result into the `core` store with `receiveEntityRecords`, so the editor's status is no longer stale. Then update local `isActive`.
  - **Deactivate:** call the REST route, then do the same refresh.
  - The confirm modals use `@wordpress/components` `Modal`, with the copy of `SetActiveModal` / `DeactivateModal`.
- `packages/blocks/src/checkout/publish-guard.ts`: lock only when `isActive`, or when the edited status is `publish` or `future`. Expose the current problem (for example through a tiny store or a shared selector) so `layout-actions` can disable Activate. Reword its messages to match the new server wording.
- `apps/admin/src/pages/layouts/`:
  - `LayoutStatusBadge.tsx` renders Active, Inactive or Archived from `isActive` and `status === 'trash'`.
  - `LayoutsPage.tsx` drops the Active and Published columns and changes its description to "Create and activate checkout layouts."
  - `SetActiveModal.tsx` button always reads "Activate".
  - `DeactivateModal.tsx` body reads: "It becomes inactive. Shoppers stop seeing it, and you can activate it again at any time."
  - `LayoutSelect.tsx` and `AssignmentDrawer.tsx` follow the badge.
- Draft wording in the SPA (`SourceStep`, `LibraryStep`, `NewLayoutModal`, `FinishStep`, `DraftStep`) changes to "Create layout", "Layout created." and "Activate it when you are happy with it."

**Data and storage:**
- No new keys. `yaycheckout_active_layout` is unchanged, and `post_status` keeps its values.
- No migration (user decision). Uninstall is unchanged.

**Integration points:**
- Existing routes `POST yaycheckout/v1/layouts/{id}/activate` and `/deactivate`. `/deactivate` now also unpublishes.
- `transition_post_status`, through `LayoutActiveGuard`.
- `wp/v2/yay_checkout_layout` saves still pass through `LayoutCoreRestController::prepare_item_for_database()`.
- `usePublishLayoutMutation` and `POST /layouts/{id}/publish` stay as they are (unused by the SPA). Removing them is not in scope.

**Edge cases and failure modes:**
- **Recursion:** the guard must not call the status-changing `deactivate()`; it uses `remove_active()`.
- **Stale editor status:** after Activate or Deactivate, the next Save would send the old status. The refresh through `receiveEntityRecords` prevents this. Acceptance criterion 6 pins it.
- **Activate refused:** if `/activate` returns 400 (rules) or 4xx (archived), show the message as an editor error notice. The status is unchanged.
- **Unsaved edits on Activate:** save first, and abort Activate if the save fails.
- **Archive of an active layout:** `archive()` → `deactivate()` (draft) → trash. Restore still returns it to draft.
- **Legacy published-inactive layout opened in the editor:** Save keeps it `publish`, so the guard applies (criterion 10 behaviour). It shows Activate.
- **Header class names change in a future WordPress:** the portal finds no target and the buttons don't render, while core's buttons stay hidden. Guard against this: if no mount point is found, do not add the hiding CSS (add a body class from JS that the CSS keys on).

**Decision record:** add `docs/decisions/2026-10-08-deactivate-unpublishes.md`. It extends `2026-10-05-layout-is-one-post.md`: merchants see Active/Inactive taken from the active set, deactivation sets the layout to draft, and the editor's publish controls belong to YayCheckout. The 2026-10-05 record stays accepted.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Save only in the editor; Activate and Deactivate only in the list | User chose Save + Activate/Deactivate in the editor for convenience. |
| Relabel core's Publish button to "Activate" | Core's sidebar status controls and Switch to draft remain, and "Publish" would still not add the layout to the active set. |
| Migrate published-inactive layouts to draft on upgrade | User chose no data change; the UI derives from `is_active`, so no migration is needed. |
| Keep blocking all saves while invalid | User chose to let inactive layouts save freely and enforce the rules at activation. |
| Activating B on Free leaves A published | Different statuses depending on how a layout became inactive; user chose draft. |
| Derive Active from `post_status` alone (drop the option) | Pro needs the ordered set for `LayoutAssignment::pick()`, and legacy published-inactive layouts would turn live. |

## Rollout

- There is no flag. Release note: "Layouts now show Active or Inactive. Deactivating a layout returns it to draft. The editor's Publish button is replaced by Save and Activate/Deactivate."
- Existing layouts are untouched.

## Open questions

None.
