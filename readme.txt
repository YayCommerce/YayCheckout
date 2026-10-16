=== YayCheckout - WooCommerce Checkout Builder ===
Contributors: yaycommerce
Tags: checkout, checkout builder, checkout fields, woocommerce
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Checkout builder plugin for WooCommerce: customize checkout fields, layout and design.

== Description ==

YayCheckout lets you customize the WooCommerce checkout page: manage fields, layout, and design with a visual builder.

== External services ==

YayCheckout connects to the services below, only from the WordPress admin. Shoppers on the checkout page never contact a third-party service.

= YayCommerce compatibility list =

The Compatibility screen fetches a list of known plugin and theme compatibility notes from `https://yaycommerce.com/yaycheckout/compatibility.json`. It is a plain request with no data from your store; like any WordPress HTTP request it carries your site's URL (in the User-Agent) and your server's IP address. It is sent when an administrator opens the Compatibility screen or clicks Refresh, and the result is cached; if the request fails, the list bundled with the plugin is used.

This service is provided by YayCommerce: [Terms and Conditions](https://yaycommerce.com/terms-and-conditions/), [Privacy Policy](https://yaycommerce.com/privacy-policy/).

= Google Fonts =

When you save a checkout layout whose design uses a Google font that is not installed on your site yet, the plugin downloads that font through the WordPress Font Library: the font list comes from WordPress.org, and the font files from Google Fonts (`fonts.gstatic.com`). The files are stored in your site's `wp-content/uploads/fonts` folder, so the checkout serves them from your own site. While you pick a font in the layout editor, your browser loads its preview directly from `fonts.gstatic.com`, which sends your IP address and browser details to Google. No store or customer data is sent.

This service is provided by Google: [Google Fonts API Terms of Service](https://developers.google.com/fonts/terms), [Privacy Policy](https://policies.google.com/privacy). The font list is provided by WordPress.org: [Privacy Policy](https://wordpress.org/about/privacy/).

== Changelog ==

= 1.0.0 =
* Initial backbone
