<?php
/**
 * Split express's logged-out view: what a guest who must log in sees, in the template's look. Printed by
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
<!-- wp:group {"style":{"dimensions":{"minHeight":"100vh"}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="min-height:100vh"><!-- wp:yaycheckout/checkout -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"0px"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"520px","style":{"color":{"background":"#1e1e1e","text":"#f0f0f0"},"spacing":{"padding":{"top":"32px","right":"40px","bottom":"32px","left":"40px"}}}} -->
<div class="wp-block-column has-text-color has-background" style="color:#f0f0f0;background-color:#1e1e1e;padding-top:32px;padding-right:40px;padding-bottom:32px;padding-left:40px;flex-basis:520px"><!-- wp:group {"style":{"spacing":{"blockGap":"28px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"elements":{"link":{"color":{"text":"#ffffff"}}},"color":{"text":"#ffffff"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"color":{"text":"#a7aaad"},"typography":{"fontSize":"15px","lineHeight":"1.6"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#a7aaad;margin-top:0;margin-bottom:0;font-size:15px;line-height:1.6"><?php esc_html_e( 'Your cart is saved. Log in and you will pick up right where you left off.', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"64px","right":"40px","bottom":"64px","left":"40px"}}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="padding-top:64px;padding-right:40px;padding-bottom:64px;padding-left:40px"><!-- wp:group {"style":{"spacing":{"blockGap":"28px"}},"layout":{"type":"constrained","contentSize":"420px"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"8px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"28px","fontWeight":"400","lineHeight":"1.2"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:28px;font-weight:400;line-height:1.2"><?php esc_html_e( 'Log in to check out', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0"><?php esc_html_e( 'You must be logged in to checkout.', 'woocommerce' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/login {"text":"<?php esc_html_e( 'Have an account? Log in to continue', 'yaycheckout' ); ?>","linkText":"<?php esc_html_e( 'Log in to continue', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"15px"},"border":{"color":"#dddddd","width":"1px","style":"solid","radius":"4px"},"spacing":{"padding":{"top":"16px","right":"20px","bottom":"16px","left":"20px"},"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:yaycheckout/return-to-cart {"buttonText":"<?php esc_html_e( 'Return to cart', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"14px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:yaycheckout/checkout --></div>
<!-- /wp:group -->
