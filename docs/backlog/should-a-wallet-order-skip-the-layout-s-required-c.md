---
id: BL-3
title: Should a wallet order skip the layout's required custom fields, or should Express Pay hide while the layout has one?
kind: decision
status: decided
from: P-2
affects: [R-6]
default: let the wallet order through without the layout's required extra fields and address flags
decision: Let wallet orders through without the layout's own required extra fields and address flags (the plan's default)
created: 2026-11-12
settled: 2026-11-12
---
# BL-3 Should a wallet order skip the layout's required custom fields, or should Express Pay hide while the layout has one?

A wallet (Apple Pay, Google Pay, Link) creates the order from the gateway's own wc-ajax call with only name, email, phone and address. A layout's required extra fields (CheckoutExtraFields::validate(), includes/Renderer/CheckoutExtraFields.php:88) and the address fields it makes required (CheckoutRequiredFields, includes/Renderer/CheckoutRequiredFields.php:35) make that order fail.

Options:
- **Let it through (default, P-2):** on a wallet order the layout's own required rules stand down; WooCommerce's and the gateway's rules still apply. Orders paid by wallet can lack a required custom field's value. FunnelKit Checkout does the same for its phone rule.
- **Hide Express Pay** while the layout holds a required extra field, with an editor notice. Data stays complete, but those layouts lose wallets.

Why it waited: a product call about merchant data versus conversion, raised by autopilot with no one to ask. P-2 builds the default; switching is one check in ExpressPay::is_wallet_order()'s callers or in the block's render.php.
