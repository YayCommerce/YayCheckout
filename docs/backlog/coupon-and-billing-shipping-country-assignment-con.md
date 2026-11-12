---
id: BL-2
title: Coupon assignment condition
kind: deferred
status: open
from: P-1
affects: []
default: 
decision:
created: 2026-11-12
---
# BL-2 Coupon assignment condition

Rules v2 (P-1, from BS-1) adds the cart subtotal, cart quantity and billing/shipping country conditions. The applied coupon condition, also a common ask, was left out:

- **Applied coupon** (`coupon in list`): needs a coupon search route beside `includes/Pro/Controllers/AssignmentOptionsRestController.php` and an async picker like products. Coupons can be applied inside the checkout form, so, like the cart types, it would be read on the page load and pinned (`includes/Renderer/LayoutPin.php`).

Why it waited: it isn't needed for R-1's acceptance, and the coupon search route and picker are extra scope P-1 doesn't take on.
