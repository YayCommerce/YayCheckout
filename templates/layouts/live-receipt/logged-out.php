<?php
/**
 * Live receipt's logged-out view: what a guest who must log in sees, in the template's look. Printed by
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
<!-- wp:group {"style":{"spacing":{"padding":{"top":"64px","right":"24px","bottom":"96px","left":"24px"},"blockGap":"20px"}},"layout":{"type":"constrained","contentSize":"420px"}} -->
<div class="wp-block-group" style="padding-top:64px;padding-right:24px;padding-bottom:96px;padding-left:24px"><!-- wp:group {"style":{"color":{"background":"#ffffff"},"border":{"radius":"2px"},"spacing":{"padding":{"top":"32px","right":"28px","bottom":"32px","left":"28px"},"blockGap":"20px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:2px;background-color:#ffffff;padding-top:32px;padding-right:28px;padding-bottom:32px;padding-left:28px"><!-- wp:group {"style":{"spacing":{"blockGap":"6px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo {"width":40} /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"typography":{"fontSize":"15px","fontWeight":"600"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px","textTransform":"uppercase","letterSpacing":"0.08em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Receipt on hold', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"top":{"color":"#cccccc","width":"1px","style":"dashed"},"bottom":{"color":"#cccccc","width":"1px","style":"dashed"}},"spacing":{"padding":{"top":"20px","bottom":"20px"},"blockGap":"6px"}}} -->
<div class="wp-block-group" style="border-top-color:#cccccc;border-top-style:dashed;border-top-width:1px;border-bottom-color:#cccccc;border-bottom-style:dashed;border-bottom-width:1px;padding-top:20px;padding-bottom:20px"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":"23px","fontWeight":"400","lineHeight":"1.3"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading has-text-align-center" style="margin-top:0;margin-bottom:0;font-size:23px;font-weight:400;line-height:1.3"><?php esc_html_e( 'Log in to check out', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#757575"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-align-center has-text-color" style="color:#757575;margin-top:0;margin-bottom:0"><?php esc_html_e( 'You must be logged in to checkout.', 'woocommerce' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/login {"text":"<?php esc_html_e( 'Have an account? Log in to continue', 'yaycheckout' ); ?>","linkText":"<?php esc_html_e( 'Log in to continue', 'yaycheckout' ); ?>","style":{"typography":{"fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/return-to-cart {"buttonText":"<?php esc_html_e( 'Return to cart', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"13px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/checkout -->
