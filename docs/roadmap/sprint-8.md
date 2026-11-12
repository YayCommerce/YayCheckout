## Sprint 8 (2027-02-18 → 2027-03-03)

Goal: Yay ecosystem first: YayCurrency and YayPricing work inside a checkout layout.

### R-11 YayCurrency integration
- status: todo
- size: M
- target: 1.2.0
- why: requested 2026-11-12 (1.2.0: Yay ecosystem); YayCurrency (YAY_CURRENCY_VERSION) has no adapter in includes/Integrations/ nor an entry in IntegrationsHelper
- acceptance: A shopper who picks a currency sees the layout's items, totals, shipping and Express Pay in that currency, and it stays correct after an update_order_review refresh.
- editions: free, pro

### R-12 YayPricing integration
- status: todo
- size: M
- target: 1.2.0
- why: requested 2026-11-12 (1.2.0: Yay ecosystem); YayPricing (YAYDP_VERSION) discounts and their messages have no adapter or plugin spot in the layout
- acceptance: YayPricing discounts and their savings messages show in the layout's order summary and refresh with the cart, and checkout is unchanged when YayPricing is inactive.
- editions: free, pro
