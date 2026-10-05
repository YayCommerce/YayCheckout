# Extra-field errors don't point at their field

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`CheckoutExtraFields::validate()` in `includes/Renderer/CheckoutExtraFields.php` adds each error as `$errors->add( 'yaycheckout_extra_' . $key, $message )`, with no data. `WC_Checkout::process_checkout()` passes each error's data to `wc_add_notice()`, and `wc_get_notice_data_attr()` (`../woocommerce/includes/wc-notice-functions.php`) turns its `id` into the `data-id` of the notice's list item in `../woocommerce/templates/notices/error.php`. In `../woocommerce/assets/js/frontend/checkout.js`, `wrapMessagesInsideLink()` uses `data-id` to link the message to the field, and `show_inline_errors()` uses it to print the message under the field and set `aria-invalid`. WooCommerce's own required-field errors in `WC_Checkout::validate_posted_data()` pass `array( 'id' => $key )`. Introduced by `873ae15` (PR #22). Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

The shopper sees "Gift note is a required field." at the top of the form, with no link, no message under the field and no `aria-invalid` on it. On a long layout they have to hunt for the field, and screen-reader users aren't told which one failed.

## Suggested fix

Pass `[ 'id' => $key ]` as the third argument of `$errors->add()`. A radio group's inputs get the ids `<key>_<option>` from `woocommerce_form_field()`, and the checkbox group that `packages/blocks/src/extra-field/render.php` prints has no element with the Key as its id. Point those at an element that exists, for example the first input, or give the group an element with that id.

## Done when

- [ ] `CheckoutExtraFields::validate()` passes an `id` in the data of each error it adds.
- [ ] Submitting the checkout with a required text extra field left empty shows the error message under that field.
- [ ] Submitting the checkout with a required radio extra field unanswered shows the error under that field.
