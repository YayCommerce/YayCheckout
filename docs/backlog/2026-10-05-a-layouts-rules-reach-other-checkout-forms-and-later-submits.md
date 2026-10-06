# A layout's field rules apply to other checkout forms and to forms rendered before a change

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

Nothing in the checkout POST says which layout rendered the form. `CheckoutRequiredFields::filter_fields()` is added with no page gate; its comment explains that the POST is validated before `is_page()` works. `CheckoutExtraFields::validate()` and `CheckoutExtraFields::persist()` read the same layout through `CheckoutPage::layout()`. `CheckoutPage::resolve()` has no page check and calls `LayoutRepository::active()` afresh on every request.

WooCommerce applies `woocommerce_checkout_fields` in `WC_Checkout::initialize_checkout_fields()` and fires `woocommerce_after_checkout_validation` in `WC_Checkout::validate_checkout()` for every classic checkout, and `wc-ajax=checkout` runs on any URL. `CheckoutRequiredFields::filter_form_field()` and `CheckoutRequiredFields::enqueue_script()` gate on `is_checkout()`, which is true on any page holding the shortcode. So:

- a `[woocommerce_checkout]` shortcode on a landing page or a funnel step is validated against the active layout's required flags and extra fields;
- a merchant's change between render and submit (activating another layout, editing the active one, or changing Pro assignment rules) changes which layout validates the order.

The no-pin comments in `CheckoutPage::resolve()` and `includes/Enums/ConditionType.php` cover only facts the shopper can change. Introduced by `40b734a` and `d2dce1c` (the overlay, squash-merged as `ff15893`), `873ae15` (extra fields, PR #22) and `3c7343b` (assignment, PR #30). Found by reading the code; not reproduced.

## Why it matters

**Severity:** high: it breaks a shopper's checkout or loses their data.

Say the active layout has a required extra field "Gift note". A shopper on a landing page with its own checkout form gets "Gift note is a required field." for a field that page never showed, and can't order. A merchant who adds that field while shoppers are mid-checkout fails their submits the same way. A filled extra field the merchant just removed is silently not saved.

## Suggested fix

Have `packages/blocks/src/checkout/render.php` print a hidden field with the layout id and its modified time. On a checkout POST, validate against that layout, in `CheckoutRequiredFields` and `CheckoutExtraFields` alike. When the field is absent, stand down, so WooCommerce validates a form we didn't render as it would without the plugin. When the posted layout is no longer active (or the valid preview), or its modified time no longer matches, ask the shopper to reload rather than name a field their page didn't show, and say so in `CheckoutPage`'s docblock. Then rewrite the no-pin comments. The fix decides which layout a submit is checked against; if it changes `LayoutAssignment::pick()`, an integration test is warranted there under CLAUDE.md's Tests rule. Ask before adding it.

## Done when

- [ ] `packages/blocks/src/checkout/render.php` prints a hidden input carrying the layout id.
- [ ] `CheckoutRequiredFields::filter_fields()` returns WooCommerce's fields unchanged on a checkout POST that carries no layout id.
- [ ] `CheckoutExtraFields::validate()` adds no error on a checkout POST that carries no layout id.
- [ ] `CheckoutExtraFields::persist()` stores the extra fields of the posted layout id, not of `LayoutRepository::active()`.
- [ ] With a required extra field on the active layout, an order placed from another page holding `[woocommerce_checkout]` succeeds without that field.
- [ ] A shopper who loaded layout A and submits after the merchant activates layout B gets no error for a field that only layout B has.
- [ ] A submit made after the merchant edited the rendered layout never fails on a field the shopper's page didn't show.
