# Release 1.2.0

[Roadmap](README.md) · [Items in 1.2.0](v1.2.0.md)

<!-- Written by hand: `yayh roadmap` never rewrites this file. Use plain words a leader or support can read: what people see, not class or table names. -->

## Goal
1.2.0 is the integrations release. A merchant who runs other Yay plugins or common WooCommerce plugins next to YayCheckout gets a checkout layout that works with them, with no workarounds. On the Yay side, YayCurrency prices and YayPricing discounts show correctly in the layout, and YayReturns' return link appears on the thank-you page. That joins YayBoost, YayReviews, YayExtra, Wholesale B2B and YayMail from 1.1.0. On the common-plugin side, checkouts can be translated with WPML or Polylang, subscriptions can be bought through a layout, more payment gateways show their wallet buttons in Express Pay, and the main third-party currency switchers are supported. The plan is seven items over three sprints (Sprints 8–10, 2027-02-18 → 2027-03-31) at one developer's capacity, 23 of 24 dev-days.

## Release criteria
<!-- Each line is something that must be true to ship, checked by /yay:release before it tags. Tick it when it's verified, with where (staging, a test site, the e2e run). -->
- [ ] Every item in this version works end to end on a test site, for every edition it names
- [ ] Every item's acceptance line in [v1.2.0.md](v1.2.0.md) is checked on a test site
- [ ] With each integrated plugin (YayCurrency, YayPricing, YayReturns, WPML, Polylang, WooCommerce Subscriptions, Aelia, CURCY, WOOCS) inactive, checkout looks and works exactly as without it
- [ ] Prices, totals and Express Pay amounts match WooCommerce's classic checkout for the same cart, in a non-default currency, before and after a checkout refresh
- [ ] The Integrations and Compatibility screens list each new plugin with the right status
- [ ] Updating from 1.1.0 keeps every saved layout, rule and setting, with no manual step
- [ ] Orders place with HPOS on and off, and with at least one express pay gateway
- [ ] Runs on PHP 7.4, the plugin's lowest supported WordPress (6.5) and WooCommerce (10.0), and the tested-up-to versions
- [ ] The Free zip contains no Pro-only code (YayReturns thank-you spot) and passes the edition boundary check and Plugin Check

## Out of scope
<!-- What people might expect in this release, and where it goes instead (a later version, Later, dropped). -->
- Newsletter opt-in and cart-abandonment capture (FunnelKit Automations, MailPoet, Mailchimp): R-18, in Later. Most of these already print on a classic hook the WooCommerce Hook block can place.
- Translation plugins other than WPML and Polylang (TranslatePress, Weglot). Later, if people ask.
- YaySwatches, YaySMTP, Notibar and FileBird: nothing of theirs shows on checkout.
- The block-based WooCommerce checkout. YayCheckout still replaces the classic checkout only.
- Cut first if late: R-17 Third-party currency switchers, then R-16 WooCommerce Subscriptions. Both are scheduled last and nothing depends on them. A cut item is retargeted to 1.3.0.

## Risks
<!-- What could go wrong for merchants or shoppers, and what limits it. -->
| Risk | Impact | Mitigation |
|---|---|---|
| A currency or pricing plugin changes prices after the layout prints them | Shoppers see one total and pay another | The release criterion above compares totals with the classic checkout, including after a refresh |
| Items cut from 1.1.0 (R-8, R-3) are retargeted to 1.2.0 | Sprints 8–10 run over capacity and 1.2.0 slips | Re-plan with `/yay:roadmap --target=1.2.0` when 1.1.0 ships, and cut R-17, then R-16 |
| A third-party plugin update changes its hooks | An integration breaks silently on a merchant's store | Every adapter checks that the plugin's classes and methods exist, and does nothing otherwise |
| Translated layouts drift from the default language after the merchant edits one | Shoppers in one language see an older checkout | Missing translations fall back to the default language's text |

## Open questions
<!-- Each has a proposal that the work follows until someone answers it. Fill Answer (with who and when) once it's decided. -->
| # | Question | Proposal | Answer |
|---|---|---|---|
| 1 | Which gateways beyond PayPal Payments does R-15 cover? | Ask support for the gateways merchants name most. Until then: PayPal Payments, then any gateway whose wallet buttons go missing on a test site | |
| 2 | Translations: one layout with translated texts, or one layout per language? | One layout, with its texts translated through WPML's and Polylang's string translation. A rules condition per language can come later | |
| 3 | Is R-18 (newsletter and abandoned-cart capture) needed in 1.2.0? | No. It stays in Later, and the Hook block covers the plugins that print on a classic hook | |
