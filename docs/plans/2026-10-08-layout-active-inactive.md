# Layout Active / Inactive Status Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-08
**Goal:** Merchants see only Active / Inactive / Archived for a layout; Deactivate returns a layout to draft; the embedded editor offers Save plus Activate or Deactivate instead of core's Publish controls.
**Spec:** [docs/specs/2026-10-08-layout-active-inactive-design.md](../specs/2026-10-08-layout-active-inactive-design.md)
**Architecture:** The badge reads the active set (`is_active`), not `post_status`, so legacy published-but-inactive layouts need no migration. `LayoutRepository::deactivate()` becomes "stop serving and unpublish", and every merchant-initiated deactivation goes through it; the hooks that react to a status change only forget the id, so nothing recurses. The editor gets its own header buttons that call the existing `activate` / `deactivate` routes, so the repository's rules stay the single gate.
**Touches:** `includes/Repositories/LayoutRepository.php`, `includes/Engine/LayoutActiveGuard.php`, `includes/Engine/LayoutTrashSync.php`, `includes/Services/LayoutMarkupReader.php`, `includes/Renderer/PreviewRequest.php`, `includes/Engine/Admin/LayoutEditorCanvas.php`, `packages/blocks/src/checkout/` (`publish-guard.ts`, new `layout-actions.tsx`, `index.ts`), `apps/admin/src/pages/layouts/`, `apps/admin/src/pages/tools/`, `apps/admin/src/components/setup-wizard/FinishStep.tsx`, `docs/architecture.md`, new `docs/decisions/2026-10-08-deactivate-unpublishes.md`. No shared code across plugins.
**Tests:** none. CLAUDE.md's test rule names `publish_error()`, `pick()`, `RequiredFieldOverlay::apply()` and `LayoutImportReview`; this change only rewords `publish_error()`'s messages, and `composer test` holds no tests today. Every task ends in a manual check.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in `boot_pro()`; nothing here touches `includes/Pro/`. The editor buttons ship to both builds; Free's single active layout comes from `set_active()`.
- WordPress floor is 6.5 (`yaycheckout.php`). The editor header is `.editor-header__settings` from 6.6 and `.edit-post-header__settings` in 6.5.
- `LayoutMarkupReader::publish_error()` is the one rule set; `packages/blocks/src/checkout/publish-guard.ts`, `packages/blocks/src/shared/extra-field-keys.ts` and `packages/blocks/src/extra-field/edit.tsx` mirror it and change with it (`docs/conventions.md`).
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks` / `./scripts/run.sh build`.
- Branch from `dev/edd` as `<name>/feat/layout-active-inactive`; Conventional Commits; each commit ends with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`; stage files by name.
- The admin lint fails on `dev/edd` (`docs/known-issues.md`): check only changed files with `npx --no-install eslint <files>` and `npx --no-install prettier --check <files>` from `apps/admin`; add no finding.

## Review focus

- **Recursion:** `deactivate()` now calls `wp_update_post()`, which fires `transition_post_status`; if `LayoutActiveGuard` still called `deactivate()` it would loop. Task 1 Step 4 checks a core-editor "draft" transition and an SPA deactivate both finish.
- **Stale editor status after Activate:** the editor still holds `status: draft`; the next Save would send it and `LayoutActiveGuard` would silently take the checkout offline. Task 4 Step 5 saves twice after Activate and checks the layout stays active.
- **Activate with unsaved edits:** the server checks the saved markup, so activating unsaved, invalid edits must not go live. Task 4 saves first and aborts on a failed save; Step 6 checks it.
- **Hidden core buttons with no replacement:** if the header mount point isn't found, core's buttons must stay visible. Task 4 keys the hiding CSS on a body class the plugin adds only after mounting; Step 7 checks it.
- **Free replacement:** activating B on Free must draft A, not only drop it. Task 1 Step 4 checks it.

---

### Task 1: Deactivation unpublishes

**Files:**
- Modify: `includes/Repositories/LayoutRepository.php` (`deactivate()` :215-228, `set_active()` :295-301, `activate()` docblock :170-175)
- Modify: `includes/Engine/LayoutActiveGuard.php:31-41` and class docblock
- Modify: `includes/Engine/LayoutTrashSync.php:24-32`

**Interfaces:**
- Produces: `LayoutRepository::forget_active( int $id ): void` — removes `$id` from `Layout::OPTION_ACTIVE` only, never touches `post_status`.
- Produces: `LayoutRepository::deactivate( int $id ): Layout` — calls `forget_active()`, then, when `post_status` is `publish`, `future` or `pending`, `wp_update_post( [ 'ID' => $id, 'post_status' => LayoutStatus::DRAFT ], true )`; throws `\RuntimeException` on a `WP_Error`. Returns the layout re-read with `is_active` false.

- [ ] **Step 1: In `LayoutRepository`, add `forget_active()` and rewrite `deactivate()` as above**

Replace the docblock's "Leaves post_status alone" with "Stops serving the layout and returns it to draft, so inactive always means unpublished for layouts deactivated from now on". `archive()` keeps calling `deactivate()` (draft, then trash; `restore()` already returns draft).

- [ ] **Step 2: Free replacement in `set_active( int $id ): void`**

Before storing on Free, call `$this->deactivate( $old )` for each id in `active_ids()` other than `$id`. Pro behaviour unchanged. Update the `activate()` docblock to say a Free activation drafts the layout it replaces.

- [ ] **Step 3: Hooks only forget the id**

`LayoutActiveGuard::deactivate_when_unpublished()` and `LayoutTrashSync::keep_trashed_layout()` call `forget_active()` instead of `deactivate()`. In the guard's class docblock, note why: the status already moved, and `deactivate()` would fire the transition again.

- [ ] **Step 4: Verify with WP-CLI from the WordPress root**

Run on a Free-plan setup (or with two layouts on Pro for the last line), ids from `wp post list --post_type=yay_checkout_layout --fields=ID,post_status`:
- Activate A, then Deactivate A in the SPA → `wp post get <A> --field=post_status` prints `draft`; `wp option get yaycheckout_active_layout` does not contain A.
- Free: activate A, then activate B → A prints `draft`, option holds only B.
- Pro: activate A and B, deactivate B → A `publish` and in the option; B `draft`.
- Guard: activate A, then `wp post update <A> --post_status=draft` → command returns (no hang), A is not in the option.
- Archive the active A from the SPA → A is `trash`; Restore → `draft`.
Expected: all as stated. Then `composer lint -- includes/Repositories/LayoutRepository.php includes/Engine/LayoutActiveGuard.php includes/Engine/LayoutTrashSync.php` → no errors.

- [ ] **Step 5: Commit**

Stage: the three PHP files above.
Message: `feat(layouts): return a layout to draft when it is deactivated`

### Task 2: "Activate" wording on the server and in the preview banner

**Files:**
- Modify: `includes/Services/LayoutMarkupReader.php:157-171` (`publish_error()` messages and docblock)
- Modify: `includes/Renderer/PreviewRequest.php:189-198` (`banner()`)
- Modify: `includes/Controllers/LayoutRestController.php:330` (docblock only)

**Interfaces:**
- Produces: `publish_error()` messages `Add a Checkout block to this layout before activating it.` and `Every extra field needs a Key before this layout can be activated.` The two Key-format messages are unchanged. Task 4 copies both strings verbatim.

- [ ] **Step 1: Reword the two messages in `publish_error()`**

Docblock: "Why the layout cannot be activated (or saved while active)".

- [ ] **Step 2: Banner label in `PreviewRequest::banner( Layout $layout )`**

The `%2$s` label is `Archived` when `$layout->post_status` is `LayoutStatus::ARCHIVED`, else `Active` when `$layout->is_active`, else `Inactive`. The previewed layout comes from `LayoutRepository::find()`, which already sets `is_active`. Translator comment: `2: layout state: Active, Inactive or Archived`. Drop the `LayoutStatus::label()` use here.

- [ ] **Step 3: Verify**

- In the SPA, Preview an inactive layout → banner reads `Preview of "<title>" (Inactive) — expires in …`. Activate it and preview again → `(Active)`.
- `wp eval 'echo YayCheckout\Services\LayoutMarkupReader::publish_error("<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->");'` → `Add a Checkout block to this layout before activating it.`
- `composer lint -- includes/Services/LayoutMarkupReader.php includes/Renderer/PreviewRequest.php includes/Controllers/LayoutRestController.php` → no errors.

- [ ] **Step 4: Commit**

Stage: the three PHP files above.
Message: `feat(layouts): say activate instead of publish in rules and preview banner`

### Task 3: SPA shows Active / Inactive

**Files:**
- Modify: `apps/admin/src/pages/layouts/LayoutStatusBadge.tsx`
- Modify: `apps/admin/src/pages/layouts/LayoutsPage.tsx` (:113 description, :564-566 headers, :631-638 cells)
- Modify: `apps/admin/src/pages/layouts/SetActiveModal.tsx:42-47`
- Modify: `apps/admin/src/pages/layouts/DeactivateModal.tsx:40`
- Modify: `apps/admin/src/pages/tools/components/LayoutSelect.tsx:108-120`
- Modify: `apps/admin/src/pages/layouts/assignment/AssignmentDrawer.tsx` (only if it relies on the old labels; it renders `LayoutStatusBadge`)
- Modify: `apps/admin/src/pages/layouts/new-layout/SourceStep.tsx:85`, `LibraryStep.tsx:52`, `NewLayoutModal.tsx:61`, `apps/admin/src/components/setup-wizard/FinishStep.tsx:32`, `apps/admin/src/pages/tools/import-modal/DraftStep.tsx:62`

**Interfaces:**
- Consumes: `LayoutSummary.isActive` and `.status` (`apps/admin/src/lib/schema/layout.schema.ts`), unchanged.

- [ ] **Step 1: `LayoutStatusBadge`**

Three cases only: `status === 'trash'` → `Archived` (`destructive-soft`); `isActive` → `Active` (`success`); otherwise `Inactive` (`outline`). Docblock: "A layout's state as merchants see it: membership in the active set, not post_status." Remove the `formatDateTime` / `sprintf` imports if unused.

- [ ] **Step 2: `LayoutsPage` table and copy**

Remove the `Active` and `Published` headers and cells; keep `Status` with the badge. Description: `Create and activate checkout layouts.` Remove now-unused imports (`formatDateTime`, `Badge`) only if nothing else in the file uses them.

- [ ] **Step 3: Modals**

`SetActiveModal`: button reads `Activating…` while pending, else `Activate`; drop the comment about pending/scheduled. `DeactivateModal` body: `Shoppers will stop seeing this layout. It becomes inactive, and you can activate it again at any time.`

- [ ] **Step 4: `LayoutSelect`**

`LayoutBadge` becomes a plain `LayoutStatusBadge` with `shrink-0` (the badge now says Active itself); keep the `success-soft` variant only if the design needs it, by passing `className`. Update its docblock.

- [ ] **Step 5: Draft wording**

`Create draft` → `Create layout` (both steps and the two code comments); `Layout draft created.` → `Layout created.`; `Your draft is ready. Nothing is live yet.` → `Your layout is ready. Nothing is live yet.`; `Publish and set active when you are happy with it.` → `Activate it when you are happy with it.`

- [ ] **Step 6: Verify**

- `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` → exit 0.
- From `apps/admin`: `npx --no-install eslint <changed files>` and `npx --no-install prettier --check <changed files>` → no findings.
- `git grep -n "'Published'\|'Draft'\|Pending review\|'Scheduled'" -- apps/admin/src/pages apps/admin/src/components` → no layout-status hits.
- With `./scripts/run.sh dev`, open YayCheckout → Layouts: one Status column showing Active / Inactive; a legacy published-but-inactive layout (`wp post update <id> --post_status=publish` on an inactive one) shows Inactive and its Activate works.

- [ ] **Step 7: Commit**

Stage: the changed SPA files by name.
Message: `feat(admin): show layouts as active or inactive`

### Task 4: Editor Save / Activate / Deactivate

**Files:**
- Create: `packages/blocks/src/checkout/layout-actions.tsx`
- Create: `packages/blocks/src/checkout/layout-actions.css` (only if the buttons need spacing beyond core's `Button` defaults)
- Modify: `packages/blocks/src/checkout/publish-guard.ts`
- Modify: `packages/blocks/src/checkout/index.ts` (import `./layout-actions`)
- Modify: `includes/Engine/Admin/LayoutEditorCanvas.php` (`EDITOR_CSS`, class docblock)

**Interfaces:**
- Consumes: `GET /yaycheckout/v1/layouts/{id}` (returns `is_active`, `status`), `POST /yaycheckout/v1/layouts/{id}/activate`, `POST /yaycheckout/v1/layouts/{id}/deactivate`; Task 2's message strings.
- Produces: `export const LAYOUT_INVALID_LOCK = 'yaycheckout-layout-invalid'` and `export function useLayoutProblem(): LayoutProblem` from `publish-guard.ts` (the existing `useSelect` body, lifted out so both plugins read the same problem).
- Produces: body class `yaycheckout-has-layout-actions`, added by `layout-actions.tsx` once its portal target is found and removed on unmount.

- [ ] **Step 1: `publish-guard.ts` locks only live layouts**

Lift the problem selector into `useLayoutProblem()`. `LayoutPublishGuard` takes `isLive: boolean` from a small shared source: the `is_active` that `layout-actions` fetched, OR `getEditedPostAttribute( 'status' )` in `publish`/`future`. Share it through a tiny `@wordpress/data` store or a module-level subscribable — implementer's choice, one place only. While a problem exists: always show the warning notice; call `lockPostSaving( LAYOUT_INVALID_LOCK )` only when `isLive`. Reword the first two messages to Task 2's strings. Update the file docblock: drafts now save freely; the rules gate activation and saves of live layouts, matching the server.

- [ ] **Step 2: Hide core's status controls in `LayoutEditorCanvas::EDITOR_CSS`**

Append, scoped to `body.yaycheckout-has-layout-actions`: `.editor-post-publish-button__button`, `.editor-post-publish-panel__toggle`, `.editor-post-save-draft`, `.editor-post-saved-state`, `.editor-post-switch-to-draft`, and `.editor-post-panel__row:has(.editor-post-status)`. Confirm the WordPress 6.5 names for the same controls (the site runs 7.1.3) and add them if they differ. Docblock: the manager and these buttons own a layout's status.

- [ ] **Step 3: `layout-actions.tsx`**

`registerPlugin( 'yaycheckout-layout-actions', { render: LayoutActions } )`, returning null unless `getCurrentPostType() === editorData.postType`.
- On mount, `apiFetch` `GET /yaycheckout/v1/layouts/${ postId }` for `is_active`. (Fetched, not printed in `window.yayCheckoutBlocks`: that global is built on `init`, before the post is known.)
- Portal target: `document.querySelector( '.editor-header__settings' ) ?? document.querySelector( '.edit-post-header__settings' )`, retried until found (header renders after plugins); prepend the buttons. Add the body class only once mounted.
- **Save:** `Button variant="primary"` → `savePost()`; disabled while `isSavingPost()` or `isPostSavingLocked()`; label `Save` / `Saving…`.
- **Activate** (when inactive): `Button variant="secondary"`, disabled while `useLayoutProblem().message !== ''`. Opens a `Modal` titled `Make "%s" your live checkout?`, body `Shoppers will start seeing this layout as soon as you confirm.`, buttons `Cancel` / `Activate`. On confirm: if `isEditedPostDirty()`, `await savePost()` and stop if `didPostSaveRequestFail()`; then POST `/activate`; then refresh.
- **Deactivate** (when active): `Modal` titled `Deactivate "%s"?`, body = Task 3's DeactivateModal text, buttons `Cancel` / `Deactivate`. On confirm: POST `/deactivate`; then refresh.
- **Refresh** after either call: `apiFetch` `GET /wp/v2/${ restBase }/${ postId }?context=edit` and `dispatch( coreStore ).receiveEntityRecords( 'postType', postType, [ record ] )`, so `getEditedPostAttribute( 'status' )` matches the server; set local `isActive` from the yaycheckout response; success snackbar `Layout activated.` / `Layout deactivated.`
- **Errors:** a rejected call shows `createErrorNotice( error.message )` and changes nothing locally.

- [ ] **Step 4: Build**

Run: `pnpm build:blocks` → exits 0 and `assets/dist/blocks/checkout/` contains the new code (no hand edits).

- [ ] **Step 5: Verify inactive → active → save in the editor**

In the SPA, create a layout and open the editor. Expected: header shows `Save` and `Activate`, no `Publish` / `Save draft`; the sidebar has no Status row and no `Switch to draft`. Save → `wp post get <id> --field=post_status` prints `draft`. Activate → confirm → header shows `Save` + `Deactivate`, status `publish`, id in `yaycheckout_active_layout`. Edit a paragraph, Save twice → still `publish` and in the option.

- [ ] **Step 6: Verify the rules**

- Inactive layout: delete the Checkout block → notice `Add a Checkout block to this layout before activating it.`, Save works (status `draft`), Activate disabled.
- Active layout: add an extra field with an empty Key → Save locked with `Every extra field needs a Key before this layout can be activated.`
- Inactive valid layout with an unsaved invalid edit → Activate disabled. Inactive layout with unsaved valid edits → Activate saves them, then activates.

- [ ] **Step 7: Verify Deactivate and the fallback**

- Active layout → Deactivate → confirm → header `Save` + `Activate`, status `draft`, not in the option. Save again → stays `draft`.
- In the editor's DevTools console run `document.body.classList.remove( 'yaycheckout-has-layout-actions' )` → core's Publish/Save button and the sidebar Status row reappear, proving the hiding depends on the mount.
- `composer lint -- includes/Engine/Admin/LayoutEditorCanvas.php` → no errors.

- [ ] **Step 8: Commit**

Stage: `packages/blocks/src/checkout/layout-actions.tsx`, `layout-actions.css` if created, `publish-guard.ts`, `index.ts`, `includes/Engine/Admin/LayoutEditorCanvas.php`.
Message: `feat(editor): replace publish controls with save, activate and deactivate`

### Task 5: Docs and decision record

**Files:**
- Create: `docs/decisions/2026-10-08-deactivate-unpublishes.md`
- Modify: `docs/architecture.md` ("Layouts: one post, a status and an active set" and the `publish-guard.ts` / `LayoutEditorCanvas` paragraphs under "Blocks and the embedded editor")

- [ ] **Step 1: Decision record**

Follow `docs/decisions/2026-10-05-decision-record-practice.md`'s format (read it first). Status `accepted 2026-10-08`. Content: merchants see Active / Inactive from the active set; deactivation (SPA, editor, Free replacement, archive) sets draft; hooks only forget the id; the editor's status controls are YayCheckout's; legacy published-but-inactive layouts are left alone. It extends, not supersedes, `2026-10-05-layout-is-one-post.md`; link the spec.

- [ ] **Step 2: `architecture.md`**

Update: `deactivate()` drafts the layout and the guard/trash sync use `forget_active()`; Free replacement drafts the old layout; the editor's Publish button no longer exists (replace "the same one the editor's Publish button … makes"); `publish-guard.ts` locks only live layouts, so the editor now matches the server for drafts; `LayoutEditorCanvas` hides core's status controls once `layout-actions.tsx` mounts; the SPA shows Active / Inactive. Link the new record.

- [ ] **Step 3: Verify**

`git grep -n "stays published\|stricter than the server\|editor's Publish button" -- docs/architecture.md` → no hits.

- [ ] **Step 4: Commit**

Stage: both doc files, plus `docs/specs/2026-10-08-layout-active-inactive-design.md` and this plan if not yet committed.
Message: `docs(layouts): record active and inactive layouts`

### Task 6: End-to-end check

- [ ] **Step 1: Full build**

Run: `./scripts/run.sh build` → exits 0.

- [ ] **Step 2: Walkthrough (spec acceptance criterion 13)**

1. In the SPA, create a layout, open the editor, Save, close → list shows `Inactive`.
2. Open it, Activate in the editor, close → list shows `Active`; the storefront checkout renders the layout.
3. Deactivate from the list → list shows `Inactive`; `wp post get <id> --field=post_status` prints `draft`; the storefront shows WooCommerce's checkout.

Expected: all three as stated.

- [ ] **Step 3: Close the plan**

Set this plan's Status to `done YYYY-MM-DD` in the PR that finishes it.
