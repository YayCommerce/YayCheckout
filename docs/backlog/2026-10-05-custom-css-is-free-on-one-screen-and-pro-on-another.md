# Custom CSS is free on the Design page but sold as Pro in the setup wizard

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

The Design page shows a Custom CSS group on every plan: `apps/admin/src/pages/design/components/TokenGroups.tsx` lists `CustomCssGroup` (`apps/admin/src/pages/design/components/CustomCssGroup.tsx`) with no `isPro` check. The server agrees: `DesignRepository::prepare()` takes `custom_css` through `Models\Design::sanitize_input()` with no `Plan` check, and `Renderer\DesignStyles::css()` prints it on the storefront.

The setup wizard's Brand step disagrees. On Free, `CustomCssField` in `apps/admin/src/components/setup-wizard/BrandStep.tsx` shows a locked box with a `ProTag`, the words "Custom CSS & token overrides", and an Upgrade button. The token overrides are free too: Free's Design page edits every token group.

The wizard came in `f35ff1e` and the Design page's group in `223ee35`.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

A Free merchant is told during setup that custom CSS needs Pro, then finds it free on the Design page. Either the wizard undersells Free, or Free gives away a Pro feature. `CLAUDE.md`'s hard rule 3 says PHP withholds Pro features, and here PHP withholds nothing.

## Suggested fix

Decide which plan custom CSS belongs to, then make both screens and the server agree.

- **If Free:** drop the Pro branch from `CustomCssField`, and drop "token overrides" from any upsell copy.
- **If Pro:** gate the Design page's group. Have `DesignRepository::prepare()`, which `POST /design` and `POST /setup` share, keep the stored `custom_css` on Free rather than take the request's; refusing outright would block a downgraded site's design saves. Stop `DesignStyles::css()` printing it on Free.

## Done when

- [ ] The Design page and the Brand step present custom CSS as the same plan: both editable on Free, or both behind `ProTag`.
- [ ] If custom CSS is Pro, `POST /yaycheckout/v1/design` and `POST /yaycheckout/v1/setup` on Free don't change the stored `custom_css`.
- [ ] If custom CSS is Pro, `Renderer\DesignStyles::css()` prints no custom CSS on Free.
