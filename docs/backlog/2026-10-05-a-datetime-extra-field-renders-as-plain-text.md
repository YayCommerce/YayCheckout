# A Date / Time extra field renders as a plain text box

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`packages/blocks/src/extra-field/render.php` maps the `datetime` type to WooCommerce type `text` (`$yaycheckout_woo_type`) before it calls `woocommerce_form_field()`, although `woocommerce_form_field()` in `../woocommerce/includes/wc-template-functions.php` renders `datetime-local`. Only the fallback for a site without WooCommerce prints a `datetime-local` input. The editor shows a picker: `previewInputType()` in `packages/blocks/src/shared/field-preview.tsx` returns `datetime-local` for it, with the placeholder `YYYY-MM-DD HH:mm`. On submit, `ExtraFieldSubmission::value()` in `includes/Services/ExtraFieldSubmission.php` special-cases checkbox, select and radio only, so a `datetime` value goes through `ExtraFieldSubmission::text()` and any text is stored. Introduced by `873ae15` (PR #22). Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

The merchant adds a Date / Time field, sees a date-time picker in the editor, and expects one on the storefront. Shoppers get a plain text box instead and type dates in any format. The order then holds values like "next Tuesday" or "05/10", which the merchant can't sort or read without guessing day-first or month-first.

## Suggested fix

Pass `datetime-local` to `woocommerce_form_field()` for a `datetime` field. In `ExtraFieldSubmission::value()`, accept a `datetime` value only in the `Y-m-d\TH:i` form that input posts, and report anything else with a message about the date, not "is not a valid selection.". Consider showing the stored value on order screens in the site's date and time format.

## Done when

- [x] `packages/blocks/src/extra-field/render.php` passes type `datetime-local` to `woocommerce_form_field()` for a `datetime` field. — `packages/blocks/src/extra-field/render.php` `$yaycheckout_woo_type` is `datetime-local` for `datetime` and feeds `$yaycheckout_args['type']`.
- [x] `ExtraFieldSubmission::value()` refuses a `datetime` value that isn't a valid date and time in `Y-m-d\TH:i` form. — `ExtraFieldSubmission::datetime()` through `DateHelper::parse()`; `collect()` in `wp eval` refuses `2026-02-30T10:00`, `next Tuesday`, `05/10` and `2026-10-05T25:00`.
- [x] A Date / Time extra field on the storefront checkout opens the browser's date-time picker. — Playwright on `?page_id=8`: `<input type="datetime-local" name="delivery_time">`, `showPicker()` runs, and the screenshot shows the native date-time box with a calendar icon.
