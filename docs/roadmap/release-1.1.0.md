# Release 1.1.0

[Roadmap](README.md) · [Items in 1.1.0](v1.1.0.md)

<!-- Written by hand: `yayh roadmap` never rewrites this file. Use plain words a leader or support can read: what people see, not class or table names. -->

## Goal
1.1.0 is the first feature release after launch, and it's mostly for Pro. Merchants get more control over which checkout each shopper sees (Rules v2). They can also move in from five competing checkout and field plugins without rebuilding their checkout. Shoppers get a faster checkout: improved express pay, address suggestions and a slide-out cart. The Yay sister plugins (YayBoost, YayReviews, YayExtra, Wholesale B2B, YayMail) work inside a YayCheckout layout, and developers get a documented API. Checkout analytics then shows merchants where shoppers drop off. The plan is ten items over seven sprints, ending 2027-02-17, at one developer's capacity (8 dev-days per sprint).

## Release criteria
<!-- Each line is something that must be true to ship, checked by /yay:release before it tags. Tick it when it's verified, with where (staging, a test site, the e2e run). -->
- [ ] Every item in this version works end to end on a test site, for every edition it names
- [ ] Every item's acceptance line in [v1.1.0.md](v1.1.0.md) is checked on a test site
- [ ] A Free zip contains none of the Pro features (rules, importers, side cart, trust blocks, analytics, address autocomplete) and passes the edition boundary check
- [ ] Updating from 1.0.0 keeps every saved layout, rule and setting, with no manual step
- [ ] With each integrated plugin (YayBoost, YayReviews, YayExtra, Wholesale B2B, YayMail) inactive, checkout looks and works exactly as without it
- [ ] Orders place with HPOS on and off, and with at least one express pay gateway
- [ ] Runs on PHP 7.4, the plugin's lowest supported WordPress (6.5) and WooCommerce (10.0), and the tested-up-to versions
- [ ] The Free zip passes Plugin Check for wordpress.org, and the readme names any outside service the release calls (address lookup)
- [ ] The public developer API is documented, and the readme changelog names it

## Out of scope
<!-- What people might expect in this release, and where it goes instead (a later version, Later, dropped). -->
- The block-based WooCommerce checkout. YayCheckout still replaces the classic checkout only.
- Importers for plugins other than the five R-2 names. They go to Later, if people ask for them.
- Analytics beyond the checkout (cart, product pages, revenue reports).
- Cut first if late: R-8 Checkout analytics, then R-3 Side cart. Both are L, they're scheduled last, and nothing depends on them. A cut item is retargeted to 1.2.0.

## Risks
<!-- What could go wrong for merchants or shoppers, and what limits it. -->
| Risk | Impact | Mitigation |
|---|---|---|
| Importers map another plugin's fields wrongly | Merchant's checkout loses or misnames fields after switching | The import is reviewed before it's saved, and it lists what it couldn't map. The original plugin's data is never changed |
| Rules v2 picks a different layout than 1.0.0 did for the same cart | Shoppers see an unexpected checkout after the update | Existing rules keep their meaning, and the update criterion above is checked against saved 1.0.0 rules |
| Address lookup service is slow or down | Shoppers can't finish typing an address | Suggestions are optional: the normal fields always work without them |
| Analytics collects shopper data | Privacy complaints and GDPR exposure | Store counts per step and field only, never personal data |
| Ten items for one developer over the holidays | The release slips past 2027-02-17 | Sprint 4 is half capacity. Cut R-8, then R-3 (above) |

## Open questions
<!-- Each has a proposal that the work follows until someone answers it. Fill Answer (with who and when) once it's decided. -->
| # | Question | Proposal | Answer |
|---|---|---|---|
| 1 | Which address lookup service does R-5 use, and who pays for the API key? | The merchant enters their own key (Google Places first). Without a key, the feature stays off | |
| 2 | Is the side cart (R-3) Pro only, or partly Free? | Pro only, as scheduled | |
| 3 | Does Rules v2 (R-1) change how existing 1.0.0 rules behave? | No: existing rules match exactly as before, and v2 only adds conditions | |
| 4 | How long is the public API (R-10) promised to stay stable? | Stable across 1.x, and anything removed is deprecated for one minor release first | |
| 5 | Which of the five importers (R-2) must ship if R-2 runs long? | CheckoutWC, FunnelKit and ThemeHigh first, and the other two can follow in 1.1.x | |
