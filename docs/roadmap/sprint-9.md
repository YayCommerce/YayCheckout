## Sprint 9 (2027-03-04 → 2027-03-17)

Goal: Multilingual checkouts, YayReturns on the thank-you page, and more wallet gateways in Express Pay.

### R-13 WPML and Polylang: translated checkout layouts
- status: todo
- size: M
- target: 1.2.0
- why: requested 2026-11-12 (1.2.0: common plugins); layout and block texts are stored per layout with no language handling (no wpml/polylang code in includes/)
- acceptance: On a WPML or Polylang site a shopper sees the layout's headings, labels and buttons in their language, and a language with no translation falls back to the default one.
- editions: free, pro

### R-14 YayReturns on the thank-you page
- status: todo
- size: S
- target: 1.2.0
- why: requested 2026-11-12 (1.2.0: Yay ecosystem); YayReturns (YAYRETURNS_VERSION) withdrawal and return links have no spot on Pro's thank-you page
- acceptance: A shopper sees YayReturns' return or withdrawal link on the layout's thank-you page, and the spot is hidden when YayReturns is inactive.
- editions: pro

### R-15 Express Pay adapters for more gateways
- status: todo
- size: S
- target: 1.2.0
- from: BL-5
- why: BL-5: gateways other than FunnelKit Stripe may print wallet buttons on a hook no block fires, or post another wallet-order marker (requested 2026-11-12, 1.2.0: common plugins)
- acceptance: Wallet buttons from WooCommerce PayPal Payments and the next most-used gateways show in the Express Pay block and complete an order, with no duplicate buttons elsewhere.
- editions: free, pro
