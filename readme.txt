=== YayCheckout – Checkout Builder for WooCommerce ===
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

YayCheckout replaces WooCommerce's checkout form with a layout you build in the block editor. Pick a starting template, move fields and sections where you want them, set colours and fonts, and publish. Shoppers see your layout; orders, payments and emails stay WooCommerce's own.

= Build the checkout with blocks =

* Billing and shipping sections whose fields you add, remove, reorder, relabel and mark required or optional.
* Email, order note, create account, different address, login reminder and terms blocks, each following WooCommerce's own settings.
* Extra fields (text, select, radio, checkbox, date and time) saved on the order and shown on the order screen and the customer's order page.
* An order summary with line items, breakdown and total that update as the cart changes, plus a coupon form, savings note and shipping methods.
* Payment methods and the Place order button from WooCommerce's classic checkout, so payment gateways that support it keep working.
* WooCommerce Hook blocks that print what other plugins add to checkout hooks, where you place them.

= Design without code =

* Templates to start from: Classic two-column, Split express, Editorial fashion, Upsell & cross-sell, Live receipt and Digital product.
* One design panel for colours, typography, inputs, buttons and notices, with Google Fonts installed into your site.
* Preview links to check a layout before shoppers see it.

= Safe to try =

* Turn the layout off, or deactivate the plugin, and WooCommerce's standard checkout returns at once.
* The Compatibility screen lists the checkout plugins and gateways that have been tested with YayCheckout.

== Installation ==

1. Install and activate WooCommerce 10.0 or later.
2. In WordPress, go to Plugins > Add New, search for "YayCheckout", then install and activate it.
3. Open YayCommerce > YayCheckout. The setup wizard asks for a template and creates your first layout.
4. Edit the layout, then activate it. Your store's checkout page now shows it.

== Frequently Asked Questions ==

= Does YayCheckout need WooCommerce? =

Yes. It requires WooCommerce 10.0 or later, and it changes nothing while WooCommerce is inactive.

= Will my payment gateway work? =

YayCheckout prints WooCommerce's classic checkout payment section, so gateways that support the classic checkout work. The Compatibility screen in the plugin lists the gateways and plugins that have been checked.

= What happens if I deactivate the plugin? =

The checkout page goes back to WooCommerce's own checkout. Your layouts stay saved and come back when you reactivate the plugin.

= Is my data removed when I delete the plugin? =

Only if you turn on "Delete all data on uninstall" in the plugin's settings. Then deleting the plugin removes its layouts and settings. Extra-field values saved on orders always stay with the orders.

== External services ==

YayCheckout connects to the services below, only from the WordPress admin. Shoppers on the checkout page never contact a third-party service.

= YayCommerce compatibility list =

The Compatibility screen fetches a list of known plugin and theme compatibility notes from `https://yaycommerce.com/yaycheckout/compatibility.json`. It is a plain request with no data from your store; like any WordPress HTTP request it carries your site's URL (in the User-Agent) and your server's IP address. It is sent when an administrator opens the Compatibility screen or clicks Refresh, and the result is cached; if the request fails, the list bundled with the plugin is used.

This service is provided by YayCommerce: [Terms and Conditions](https://yaycommerce.com/terms-and-conditions/), [Privacy Policy](https://yaycommerce.com/privacy-policy/).

= Google Fonts =

When you save a checkout layout whose design uses a Google font that is not installed on your site yet, the plugin downloads that font through the WordPress Font Library: the font list comes from WordPress.org, and the font files from Google Fonts (`fonts.gstatic.com`). The files are stored in your site's `wp-content/uploads/fonts` folder, so the checkout serves them from your own site. While you pick a font in the layout editor, your browser loads its preview directly from `fonts.gstatic.com`, which sends your IP address and browser details to Google. No store or customer data is sent.

This service is provided by Google: [Google Fonts API Terms of Service](https://developers.google.com/fonts/terms), [Privacy Policy](https://policies.google.com/privacy). The font list is provided by WordPress.org: [Privacy Policy](https://wordpress.org/about/privacy/).

== Source code ==

The JavaScript and CSS in `assets/dist/admin/` are built from `apps/admin/` (React, Vite, TypeScript), and those in `assets/dist/blocks/` from `packages/blocks/` (`@wordpress/scripts`). Neither is minified or obfuscated by hand: both are the plain output of those two builds.

The full, human-readable source of both, with the build tooling and configuration, is public at [github.com/YayCommerce/YayCheckout](https://github.com/YayCommerce/YayCheckout). This release was built from commit `@SOURCE_COMMIT@` of that repository.

To rebuild the assets from that source, use Node 22 and pnpm 11, and run these from the repository root:

1. `pnpm install`
2. `pnpm build:admin`
3. `pnpm build:blocks`

That writes the same `assets/dist/admin/` and `assets/dist/blocks/` this plugin ships.

== Changelog ==

= Nov 6, 2026 - Version 1.0.0 =
- Added: Checkout layouts built from blocks in the block editor replace WooCommerce's checkout form, and WooCommerce's own checkout returns as soon as the layout is turned off.
- Added: A setup wizard picks a template and creates your first layout.
- Added: Billing, shipping, email, order note, create account, different address, login reminder and terms blocks, each following WooCommerce's own settings.
- Added: Extra fields (text, select, radio, checkbox, date and time) are saved on the order and shown on the order screen and the customer's order page.
- Added: An order summary whose items, breakdown and total update with the cart, plus a coupon form, savings note and shipping methods.
- Added: Payment methods, express payment buttons and the Place order button come from WooCommerce's classic checkout, so gateways that support it keep working.
- Added: WooCommerce Hook blocks print what other plugins add to checkout hooks, where you place them.
- Added: YayBoost's order bump, frequently bought together and free shipping bar can be placed in a layout as blocks.
- Added: Any block can be shown or hidden on conditions set in its Visibility panel.
- Added: Classic two-column, Split express, Editorial fashion, Upsell & cross-sell, Live receipt and Digital product templates to start from.
- Added: Multi-step wizard, Accordion one-page, Minimal steps, Soft steps and Material stepper templates to start from. (Pro)
- Added: Multi-step checkouts with a steps progress bar, next and back buttons, and links to any step. (Pro)
- Added: One design panel for colours, typography, inputs, buttons and notices, with Google Fonts installed into your site.
- Added: Preview links to check a layout before shoppers see it.
- Added: Several layouts can be active at once, each assigned by customer role, login status, device, or the products and categories in the cart. (Pro)
- Added: A thank-you page built from blocks, with order details, customer details, an Order again button and a Save as PDF button. (Pro)
- Added: Layouts can be exported and imported, with a review of what an imported layout needs before it is saved. (Pro)
- Added: The Compatibility screen lists the checkout plugins and gateways that have been tested with YayCheckout.
- Added: A "Delete all data on uninstall" setting removes layouts and settings when the plugin is deleted.
