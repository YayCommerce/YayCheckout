---
id: BL-6
title: Should a mid-form billing or shipping country change re-swap the layout?
kind: decision
status: decided
from: P-1
affects: []
default: pinned at page load; mid-form country change does not swap layout
decision: Pinned at page load: country is read from the customer's stored country at pick time and a mid-form country change does not swap the layout; the next checkout page load re-picks
created: 2026-11-12
settled: 2026-11-12
---
# BL-6 Should a mid-form billing or shipping country change re-swap the layout?

The billing and shipping country conditions P-1 adds are read when the checkout page loads, from the customer's stored country (`WC()->customer`, falling back to the store's default or geolocated location), and the layout picked then is pinned for the form (`includes/Renderer/LayoutPin.php`). The shopper can change the country inside the form, and the `update_order_review` refresh could re-pick and swap the layout.

Options: (a) keep the pin: a mid-form country change does not swap the layout, the next page load re-picks; (b) re-pick on `update_order_review` and swap the layout mid-form, which re-renders the form around fields the shopper is typing into and breaks hard rule 5's fragment model.

The user chose (a) on the R-1 autopilot sheet.
