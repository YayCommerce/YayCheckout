# Moving between Free and Pro changes which shoppers see a layout

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

- **Pro to Free.** On Free, `LayoutRepository::active_ids()` keeps only the last stored id, and `LayoutRepository::active()` serves it as `$layouts[0]` without calling `LayoutAssignment::pick()`. Its rules stay stored in `Layout::META_RULES` but no longer apply. So after a downgrade the most recently activated layout serves every shopper, even if its rules limited it to, say, wholesale customers. The `active_ids()` docblock says keeping the last id is intended; it doesn't say the rules stop applying.
- **Pro to Free to Pro.** On Free, `is_active()` sees only that last id, but the older Pro ids stay in `Layout::OPTION_ACTIVE` until something rewrites the option. Moving one of them to draft on Free doesn't drop it: `LayoutActiveGuard::deactivate_when_unpublished()` asks `is_active()`, which says no. Back on Pro, `active()` loads every stored id with `find()`, which ignores `post_status`, and `pick()` can serve that draft.

The Free branch of `active_ids()` came in `e071d7d`.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

A downgrade silently widens a layout's audience: retail shoppers can get a checkout built for wholesale. The Free Layouts page has no Assignment column (`apps/admin/src/pages/layouts/LayoutsPage.tsx`), so the merchant can't see why. The way back to Pro can serve a draft the merchant took off the storefront, though `docs/decisions/2026-10-05-layout-is-one-post.md` makes `post_status` the only answer to whether shoppers may see a layout.

## Suggested fix

On Free, pass the one active layout through `LayoutAssignment::pick()` too, so a shopper its rules exclude gets WooCommerce's checkout. Have the Free Layouts page say when the active layout has rules it can't edit. Have `active()` skip any layout whose `post_status` is neither `publish` nor `future`, whatever the plan. If the team would rather Free keep serving everyone, say so in the `active_ids()` docblock and to the merchant, and change the first box.

This changes layout assignment (`LayoutAssignment::pick()` and its caller), so an integration test is warranted there under `CLAUDE.md`'s Tests rule.

## Done when

- [ ] On Free, `LayoutRepository::active()` doesn't serve a layout with assignment rules to a shopper those rules exclude.
- [ ] On Free, the Layouts page shows when the active layout has assignment rules.
- [ ] `LayoutRepository::active()` serves no layout whose `post_status` is other than `publish` or `future`.
- [ ] After Pro, then Free, then Pro, a layout that was active on Pro and moved to draft while on Free doesn't serve the checkout.
