---
id: BL-4
title: Express Pay block styling options: heading, button height and theme, per-gateway toggles
kind: deferred
status: open
from: P-2
affects: [R-6]
default: 
decision:
created: 2026-11-12
---
# BL-4 Express Pay block styling options: heading, button height and theme, per-gateway toggles

BS-2 option B: block attributes for a heading, button height and theme, and per-gateway on/off, passed into each gateway through its params filter (wc_stripe_express_checkout_params, FunnelKit's fkwcs_localized_data), with editor controls and a design (/yay:design).

Where: packages/blocks/src/express-pay/ (block.json, edit.tsx), includes/Renderer/ExpressPay.php, the integrations.

Why it waited: P-2 first makes the buttons show and the orders complete (R-6's acceptance). Free or Pro is a pricing call.
