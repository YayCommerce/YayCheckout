---
id: B-1
title: Block style values with a semicolon add declarations to the inline style
status: fixed
severity: low
editions: [free, pro]
found_in:
fixed_in:
branch: fix/block-style-values-with-a-semicolon-add-declaratio
pr: https://github.com/YayCommerce/YayCheckout/pull/286
created: 2026-11-12
---
# B-1 Block style values with a semicolon add declarations to the inline style

## Symptoms
A Payment Methods (or Shipping Methods, Order Summary) block whose style attribute holds a value with `;`, such as `border.radius = "4px;position:fixed"`, prints that extra declaration in the block's inline `style`. Found by the baseline unit test, not by a report.

## Environment
`dev` at 3c454cb, both editions. Any WordPress and WooCommerce version: the code path is plugin-only.

## Repro steps
1. `vendor/bin/phpunit -c tests/php/unit.xml.dist --filter test_refuses_a_value_that_adds_a_declaration` (remove its `markTestIncomplete` line first).
2. `CheckoutFragments::payment_methods_style_attribute( [ 'style' => [ 'border' => [ 'radius' => '4px;position:fixed' ] ] ] )` returns `--yaycheckout-payment-radius:4px;position:fixed`; expected no radius declaration.

## Root cause
`CheckoutFragments::css_safe_token()` (`includes/Renderer/CheckoutFragments.php:431-438`) says it "Rejects values that could break out of a CSS custom-property declaration", but its pattern `/[=<>{}]|\/\*/` lets `;` through. `css_length()` (`:397-407`), `css_color()` (`:412-429`) and `css_gap()` (`:391`) return such a value, and `payment_methods_style_attribute()` joins the declarations with `;` (`:310`), so the value ends its declaration and starts another. Only someone who can edit layouts (`manage_woocommerce`) can set the attribute, and they can already add custom CSS, so the impact is low. Confidence: high.

## Fix options
1. Add `;` (and `\\`) to `css_safe_token()`'s pattern, as `LayoutDesign::GRADIENT_PATTERN` already refuses them (`includes/Models/LayoutDesign.php:20-25`).

## Regression test proposal
[unit] `CheckoutFragmentsTest::test_refuses_a_value_that_adds_a_declaration` (`tests/php/unit/Renderer/CheckoutFragmentsTest.php`), already written and marked incomplete; `/yay:fix B-1` removes the mark.

## Fix
`css_safe_token()` (`includes/Renderer/CheckoutFragments.php:433`) now also refuses `;` and `\`, the same characters `LayoutDesign::GRADIENT_PATTERN` refuses. A refused value prints no declaration at all, as a value with `{` or `/*` already did. Every block style value reaches the inline style through `css_length()`, `css_color()` or `css_gap()`, and all three end in `css_safe_token()`. That covers Payment Methods, Shipping Methods, the Order Summary blocks and the Pro Steps Progress, Thank-you Text and Order Details blocks, so the one pattern is enough.

Verified: `CheckoutFragmentsTest::test_refuses_a_value_that_adds_a_declaration` (no longer incomplete, plus a `\3b` case and a `0.5rem` length that must still print) fails with the fix stashed and passes with it. The unit suite passes: 22 tests, 94 assertions.
