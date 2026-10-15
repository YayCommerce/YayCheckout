<?php
/**
 * Soft steps's logged-out view: what a guest who must log in sees, in the template's look. Printed by
 * LayoutTemplateCatalogue::logged_out_markup() in place of the shared templates/layouts/logged-out.php.
 *
 * To update it, build it in the editor's Logged out view, copy it from the Code editor and paste it
 * here, then put back the PHP: translated text and the logo-or-title choice. Keep the markup as the
 * editor saves it: a hand edit that differs by one class or style makes the editor flag the block as invalid.
 */

defined( 'ABSPATH' ) || exit;

// The block markup prints as the editor saves it, so a PHP tag inside a branch can't be indented.
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- The login message is WooCommerce's own, so its translation applies.
?>
<!-- wp:yaycheckout/checkout -->
<!-- wp:group {"style":{"border":{"bottom":{"color":"#e0e0e0","width":"1px","style":"solid"}},"spacing":{"padding":{"left":"24px","right":"24px"}}},"layout":{"type":"constrained","contentSize":"1080px"}} -->
<div class="wp-block-group" style="border-bottom-color:#e0e0e0;border-bottom-style:solid;border-bottom-width:1px;padding-right:24px;padding-left:24px"><!-- wp:group {"style":{"dimensions":{"minHeight":"56px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="min-height:56px"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo {"width":24} /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"typography":{"fontSize":"17px","fontWeight":"600","letterSpacing":"-0.01em"}}} /-->
<?php endif; ?>

<!-- wp:yaycheckout/return-to-cart {"buttonText":"<?php esc_html_e( 'Cart', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"14px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"80px","right":"24px","bottom":"96px","left":"24px"},"blockGap":"32px"}},"layout":{"type":"constrained","contentSize":"560px"}} -->
<div class="wp-block-group" style="padding-top:80px;padding-right:24px;padding-bottom:96px;padding-left:24px"><!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"34px","fontWeight":"600","lineHeight":"1.15","letterSpacing":"-0.02em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:34px;font-weight:600;letter-spacing:-0.02em;line-height:1.15"><?php esc_html_e( 'Sign in to check out', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"16px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:16px"><?php esc_html_e( 'You must be logged in to checkout.', 'woocommerce' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"color":{"background":"#f5f5f7"},"border":{"radius":"12px"},"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:12px;background-color:#f5f5f7;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:yaycheckout/login {"text":"<?php esc_html_e( 'Have an account? Sign in to continue', 'yaycheckout' ); ?>","linkText":"<?php esc_html_e( 'Sign in to continue', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"15px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/checkout -->
