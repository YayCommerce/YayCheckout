## Sprint 10 (2027-03-18 → 2027-03-31)

Goal: Subscriptions and third-party currency switchers, then the 1.2.0 release. Currency switchers are first to cut if it runs late.

### R-16 WooCommerce Subscriptions support
- status: todo
- size: M
- target: 1.2.0
- why: requested 2026-11-12 (1.2.0: common plugins); a subscription cart needs recurring totals and account creation, which the layout's summary and account blocks don't handle today
- acceptance: A shopper buys a subscription through a layout and sees its recurring totals and first renewal date, and a guest is asked to create an account when the subscription needs one.
- editions: free, pro

### R-17 Third-party currency switchers (Aelia, CURCY, WOOCS)
- status: todo
- size: M
- target: 1.2.0
- depends: R-11
- why: requested 2026-11-12 (1.2.0: common plugins); Aelia Currency Switcher is installed next to this plugin, and the approach reuses R-11's YayCurrency adapter
- acceptance: With Aelia, CURCY or WOOCS active, the layout's prices, totals and Express Pay follow the shopper's chosen currency, including after a refresh.
- editions: free, pro
