# Two writes to the active set at once can lose one of them

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`LayoutRepository::set_active()` and `LayoutRepository::deactivate()` read `Layout::OPTION_ACTIVE` through `active_ids()`, change the list in PHP, and write it back with `store_ids()`. Nothing locks between the read and the write.

On Pro, two activations that overlap can each read the same list and write it back with only their own id added, so one activation is lost. The same holds when an activation overlaps a deactivation by `LayoutActiveGuard::deactivate_when_unpublished()` or `LayoutTrashSync::keep_trashed_layout()`, so a layout moved to draft can stay in the set. The interleaving is inferred.

`DesignRepository` has the same shape. `save()` reads the design option in `prepare()` and writes all of it back in `store()`, so a preset that Pro's `DesignRepository::add_preset()` saves in between is lost.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

It needs two writes within moments of each other: two merchants, or a merchant and a script. When it happens, an activation that reported success isn't live, or a draft stays in the active set. `LayoutRepository::find()` ignores `post_status`, so Pro's `LayoutAssignment::pick()` can then serve that draft.

## Suggested fix

Hold a lock around each read-modify-write, and read the option fresh inside it; MySQL's `GET_LOCK()` through `$wpdb` is one way. With a persistent object cache, the fresh read must get past the cached `alloptions` (inferred). The `post_status` check that `docs/backlog/2026-10-05-switching-between-free-and-pro-changes-who-sees-a-layout.md` suggests for `active()` also stops a lost deactivation from serving a draft.

## Done when

- [ ] `LayoutRepository::set_active()` reads and writes `Layout::OPTION_ACTIVE` under one lock.
- [ ] `LayoutRepository::deactivate()` reads and writes `Layout::OPTION_ACTIVE` under the same lock.
- [ ] `DesignRepository::store()` keeps a preset that `DesignRepository::add_preset()` saved after `prepare()` read the option.
