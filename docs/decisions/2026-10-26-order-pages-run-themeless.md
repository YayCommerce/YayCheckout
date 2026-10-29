# The checkout and the order pages a layout renders load a bundled minimal theme in place of the site's

**Purpose:** why, on the requests a layout renders (the checkout form, its wc-ajax calls, and the order-received and order-pay pages while the active layout has a page for them), WordPress loads YayCheckout's bundled theme instead of the site's theme, why the swap changes the theme slug rather than only its directories, why the layout editor swaps with it, and what that costs. It supersedes [the checkout runs themeless record](2026-10-16-checkout-runs-themeless.md), whose decisions it restates with order-received and order-pay added.

**Update when:** never edit it. Supersede it with a new record if the checkout loads the site's theme again by default, if the swap moves off `setup_theme` or stops changing the slug, or if themeless reaches requests beyond those listed in Purpose and the layout editor.

## Status

Proposed, 2026-10-26.

## Context

- The takeover replaced only the page template. The theme still loaded: its `functions.php` hooked WooCommerce's checkout fields, fragments and templates, its stylesheets printed through `wp_head()`, and its `theme.json` global styles applied. `checkout-base.css` carried rules that undo specific themes (Storefront's floated order review, classic themes' half-width rows, focus rings on every `:focus`). [The base styles record](2026-10-11-checkout-base-styles-and-heading-font.md) accepts that a theme with stronger selectors can still win.
- Page builders that own the whole page solve the same conflict by loading no theme at all. Oxygen returned a fake theme directory from the `template_directory` filter, and Breakdance ships a "themeless" mode.
- Core includes the theme's `functions.php` right after `setup_theme`, so anything that keeps it out must decide by then. The main query doesn't exist yet, so the checkout can only be recognised from the URL.
- The superseded record kept order-received and order-pay on the site's theme, because only WooCommerce's own templates rendered them. A layout now carries a thank-you page and a pay-order page, built from its blocks with its design, and they render in our own documents (`templates/thankyou-page.php`, `templates/pay-order-page.php`). On the site's theme they would meet the same conflicts the checkout met, and a shopper would see the checkout and the pages after it in two different looks.
- Options turned down:
  - Dequeuing the theme's assets after the fact: `functions.php` and its hooks still run, and each theme names its handles differently.
  - Filtering only `template_directory` and `stylesheet_directory`, as Oxygen did: `wp_get_theme()`, `wp_is_block_theme()` and the `theme.json` resolver still read the real theme, so its global styles still print and the two halves disagree.
  - A setting in the SPA: there are no shipped users to protect, and one rendering path is easier to support. A filter covers the rare site that needs the theme.
  - Swapping only the page request: the theme's hooks would still run on `update_order_review` and the order submit, so the fragments and validation could differ from the page that rendered.
  - Swapping order-received and order-pay whenever a layout is active: an active layout without a thank-you or pay-order page shows WooCommerce's own template, which expects the site's theme around it.

## Decision

- On `setup_theme`, `Renderer\ThemelessCheckout` swaps the theme when the request is the checkout form (matched from the URL, endpoints excluded) or one of its wc-ajax calls, and a layout resolves for it exactly as `CheckoutPage` would resolve one, preview links included. Admin, admin-ajax, REST, cron, the customizer, XML-RPC and WP-CLI never swap.
- It also swaps on order-received when safe mode is off and the active layout has a thank-you page, and on order-pay with `pay_for_order` when safe mode is off and the active layout has a pay-order page: the requests `ThankYouPage` and `PayOrderPage` take over. Otherwise order-received and order-pay keep the site's theme.
- The swap changes the theme slug and roots, as core's customizer theme preview does, to `yaycheckout-themeless` in `templates/themeless/`. The directory is registered only on that request.
- The bundled theme is classic: a `theme.json` that follows Twenty Twenty-Five with a system font stack, the theme supports WooCommerce's markup needs, and an `index.php` that prints the page content in a bare document as a safety net.
- It is on by default. The `yaycheckout_themeless_checkout` filter turns it off and must be hooked from a plugin or mu-plugin.
- The layout editor swaps too, on a layout's editor screen and on the REST calls the embedded editor tags, so the canvas shows what shoppers get.
- The base styles keep their rules and their theme-following fallbacks. Under themeless they follow the bundled theme. With the filter off they follow the site's theme as before.

## Consequences

- The site's theme can no longer restyle, reorder or re-hook the checkout or the order pages a layout renders, so a layout looks the same on every site, and the checkout, thank-you and pay-order pages look alike. Theme-specific workarounds stop growing.
- A merchant's theme fonts, colours and spacing no longer reach those pages. An empty design token falls back to the bundled theme's presets, so every layout starts from the same neutral look.
- The layout editor's canvas and the storefront read the same theme. A plugin script that relied on the site theme's editor setup can behave differently in the canvas; `brandy-blocks`' Map script throws there, an observer race of its own.
- The theme's `woocommerce/` template overrides and hooks don't run on these requests. That includes order emails sent during the `wc-ajax=checkout` submit, which use WooCommerce's own email templates rather than the theme's, and a theme's `checkout/form-pay.php` or `checkout/thankyou.php` override.
- On these requests, plugins that branch on `wp_get_theme()` or theme mods see the bundled theme and empty mods. A gateway that prints on the order pages still prints, because the takeovers fire WooCommerce's hooks there.
- A URL the matcher misses (a translated checkout slug, the checkout as the front page, a renamed endpoint the matcher can't read) keeps the site's theme, as before. A false match that the takeover then declines gets the bare `index.php` document, not a blank page.
