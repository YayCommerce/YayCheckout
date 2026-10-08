# Merchants see Active or Inactive, and deactivating a layout unpublishes it

**Purpose:** why merchants see a layout as Active or Inactive instead of WordPress's post statuses, why deactivating a layout returns it to draft, and why the layout editor carries its own Save, Activate and Deactivate in place of core's Publish controls.

**Update when:** never edit it. Supersede it with a new record if merchants see post statuses again, if deactivating stops changing `post_status`, or if the editor hands status back to core's controls.

## Status

Accepted, 2026-10-08, by Trace Bui.

## Context

- A layout's visibility is its `post_status`, and which layouts serve the storefront is the active set in `Layout::OPTION_ACTIVE` ([a layout is one post](2026-10-05-layout-is-one-post.md)). `LayoutRepository::activate()` already published a layout before adding it to the set.
- Deactivating only removed the layout from the set and left it published. That made a third state, published but not serving, and the admin showed both a status badge (Published, Draft, Pending review, Scheduled) and an Active column. Merchants couldn't tell which one decided what shoppers see.
- The embedded editor was core's, with core's Publish, Update, Save draft and Switch to draft. Publish on an inactive layout made exactly that third state. Switch to draft on the active layout silently took the checkout offline through `LayoutActiveGuard`.
- Stores already running the plugin have layouts that are published but not active.
- Options turned down:
  - Saving only in the editor, with Activate and Deactivate on the layouts list alone: more steps for the common edit-then-go-live flow.
  - Relabelling core's Publish button as Activate: core's sidebar status controls and Switch to draft would remain, and publishing still doesn't add a layout to the active set.
  - Migrating published-but-inactive layouts to draft on upgrade: a data change on every store, which isn't needed once the admin reads the active set.
  - Deriving Active from `post_status` alone and dropping the option: Pro needs the ordered set for `LayoutAssignment::pick()`, and legacy published-but-inactive layouts would turn live.
  - Leaving the replaced layout published when Free activates another: its status would then depend on how it became inactive.

## Decision

- Merchants see one state per layout: Active when it is in the active set, Inactive when it isn't, Archived when its status is `trash`. No merchant-facing screen shows Published, Draft, Pending review or Scheduled. `post_status` stays the stored truth and the wire `status`.
- `LayoutRepository::deactivate()` stops serving the layout and moves a published, pending or scheduled layout to `draft`. Every merchant action that ends serving goes through it: Deactivate on the list or in the editor, Free replacing its active layout, and archiving.
- Hooks that react to a status WordPress has already changed (`LayoutActiveGuard`, `LayoutTrashSync`) only remove the id from the set through `LayoutRepository::forget_active()`, because `deactivate()` would change the status again from inside the transition.
- Existing layouts are not migrated. A published layout outside the active set shows as Inactive, and Activate works on it as on a draft.
- The layout editor's status belongs to YayCheckout: Save keeps the current status, and Activate and Deactivate call the same REST routes as the list. Core's Publish, Save draft, Switch to draft and Status controls are hidden, and only once YayCheckout's buttons have mounted.
- The rules of `LayoutMarkupReader::publish_error()` gate activation and every save of a live (published or scheduled) layout. A draft is inactive, so it saves freely, in the editor as on the server.

## Consequences

- Merchants answer "do shoppers see this?" from one label, and Activate and Deactivate are the only words for going live and stopping.
- Deactivating loses a layout's published state, so reactivating it publishes it again and re-checks the rules.
- The editor's header buttons depend on core's header markup (`.editor-header__settings`, `.edit-post-header__settings` before 6.6). If a later WordPress renames it, the buttons don't mount and core's controls stay visible, so a merchant can still save but can publish without activating until the selectors are updated.
- After Activate or Deactivate the editor must reload the post record, or its next save would send the old status. `layout-actions.tsx` owns that refresh.
- Legacy published-but-inactive layouts keep `publish` on the wire, so code must read `is_active`, not `status`, to know whether a layout serves.
