---
id: BL-5
title: Express Pay adapters for gateways beyond FunnelKit Stripe that print wallet buttons on a hook no block fires
kind: deferred
status: open
from: P-2
affects: [R-15]
default: 
decision:
created: 2026-11-12
---
# BL-5 Express Pay adapters for gateways beyond FunnelKit Stripe that print wallet buttons on a hook no block fires

P-2 adds ExpressPay's adoption list and one adapter, Integrations\FunnelKitStripe (its "inline" position hooks woocommerce_checkout_billing, which no block fires). Other gateways may do the same, or post a different wallet-order marker than payment_request_type / express_checkout_type / express_payment_type.

Where: one registration line per gateway in includes/Integrations/, plus a marker in ExpressPay's wallet-order filter if needed.

Why it waited: only FunnelKit Stripe and PayPal Payments are installed locally; WooCommerce Stripe and WooPayments print on "before customer details" already. Support should name the gateways merchants use.
