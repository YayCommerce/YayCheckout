<?php
/**
 * The shared pay-order page: what a shopper sees on order-pay (a "Customer payment page" link) while the
 * layout is active, for a layout whose template ships no pay-order.php of its own. Printed by
 * LayoutTemplateCatalogue::pay_order_markup(), which seeds a new layout with it and gives it to the
 * editor's Pay order view while a layout has none saved. Payment, Terms and Place order may only sit in
 * Checkout, which on order-pay prints a plain wrapper inside WooCommerce's pay form. Place order has no label
 * of its own, so on order-pay it reads WooCommerce's pay button text.
 *
 * To update it, build it in the editor's Pay order view, copy it from the Code editor and paste it
 * here, then put back the PHP: translated text and the logo-or-title choice. Keep the markup as the
 * editor saves it: a hand edit that differs by one class or style makes the editor flag the block as invalid.
 */

defined( 'ABSPATH' ) || exit;

// The block markup prints as the editor saves it, so a PHP tag inside a branch can't be indented.
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"48px","right":"24px","bottom":"48px","left":"24px"},"blockGap":"24px"}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group" style="padding-top:48px;padding-right:24px;padding-bottom:48px;padding-left:24px"><!-- wp:group {"layout":{"type":"flex","justifyContent":"left"}} -->
<div class="wp-block-group"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"typography":{"fontSize":"18px","fontWeight":"600"}}} /-->
<?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"28px","fontWeight":"600","lineHeight":"1.3"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:28px;font-weight:600;line-height:1.3"><?php esc_html_e( 'Pay for order', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0"><?php esc_html_e( 'Order #:', 'yaycheckout' ); ?> <span class="yaycheckout-live-value" data-yaycheckout-value="order_number" data-fallback="" data-prefix="" data-suffix=""><?php esc_html_e( 'Order number', 'yaycheckout' ); ?></span></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/checkout -->
<!-- wp:yaycheckout/order-details /-->

<!-- wp:yaycheckout/express-pay {"blockId":"exprspay"} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"},"yaycheckoutVisibility":{"action":"hide","rules":[{"type":"block","block":"exprspay","is":"empty"}]}} -->
<div class="wp-block-group"><!-- wp:separator {"backgroundColor":"yaycheckout-border","style":{"spacing":{"margin":{"top":"0","bottom":"0"}},"layout":{"selfStretch":"fill","flexSize":null}}} -->
<hr class="wp-block-separator has-text-color has-yaycheckout-border-color has-alpha-channel-opacity has-yaycheckout-border-background-color has-background" style="margin-top:0;margin-bottom:0"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-align-center" style="margin-top:0;margin-bottom:0;font-size:12px"><?php esc_html_e( 'OR', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:separator {"backgroundColor":"yaycheckout-border","style":{"spacing":{"margin":{"top":"0","bottom":"0"}},"layout":{"selfStretch":"fill","flexSize":null}}} -->
<hr class="wp-block-separator has-text-color has-yaycheckout-border-color has-alpha-channel-opacity has-yaycheckout-border-background-color has-background" style="margin-top:0;margin-bottom:0"/>
<!-- /wp:separator --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/payment /-->

<!-- wp:yaycheckout/terms /-->

<!-- wp:yaycheckout/place-order /-->
<!-- /wp:yaycheckout/checkout --></div>
<!-- /wp:group -->
