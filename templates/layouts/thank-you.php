<?php
/**
 * The shared thank-you page: what a shopper sees on order-received while the layout is active, for a
 * layout whose template ships no thank-you.php of its own. Printed by LayoutTemplateCatalogue::thank_you_markup(),
 * which seeds a new layout with it and gives it to the editor's Thank you view while a layout has none saved.
 *
 * To update it, build it in the editor's Thank you view, copy it from the Code editor and paste it
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
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:28px;font-weight:600;line-height:1.3"><?php esc_html_e( 'Thank you. Your order has been received.', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0"><?php esc_html_e( 'Order number:', 'yaycheckout' ); ?> <span class="yaycheckout-live-value" data-yaycheckout-value="order_number" data-fallback="" data-prefix="" data-suffix=""><?php esc_html_e( 'Order number', 'yaycheckout' ); ?></span></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/order-details /-->

<!-- wp:yaycheckout/order-customer-details /--></div>
<!-- /wp:group -->
