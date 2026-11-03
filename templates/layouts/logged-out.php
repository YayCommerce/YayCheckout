<?php
/**
 * The shared logged-out view: what a guest who must log in sees, for a layout whose template ships no
 * logged-out.php of its own. Printed by LayoutTemplateCatalogue::logged_out_markup(), which seeds a new
 * layout with it and gives it to the editor's Logged out view while a layout has none saved.
 *
 * To update it, build it in the editor's Logged out view, copy it from the Code editor and paste it
 * here, then put back the PHP: translated text and the logo-or-title choice. Keep the markup as the
 * editor saves it: a hand edit that differs by one class or style makes the editor flag the block as invalid.
 */

defined( 'ABSPATH' ) || exit;

// The block markup prints as the editor saves it, so a PHP tag inside a branch can't be indented.
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect
?>
<!-- wp:yaycheckout/checkout -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"72px","right":"24px","bottom":"96px","left":"24px"},"blockGap":"24px"}},"layout":{"type":"constrained","contentSize":"440px"}} -->
<div class="wp-block-group" style="padding-top:72px;padding-right:24px;padding-bottom:96px;padding-left:24px"><!-- wp:group {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-group"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"typography":{"fontSize":"18px","fontWeight":"600"}}} /-->
<?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"color":"#dddddd","width":"1px","style":"solid","radius":"8px"},"color":{"background":"#ffffff"},"spacing":{"padding":{"top":"40px","right":"32px","bottom":"40px","left":"32px"},"blockGap":"24px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-border-color has-background" style="border-color:#dddddd;border-style:solid;border-width:1px;border-radius:8px;background-color:#ffffff;padding-top:40px;padding-right:32px;padding-bottom:40px;padding-left:32px"><!-- wp:group {"style":{"spacing":{"blockGap":"8px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":"24px","fontWeight":"600","lineHeight":"1.3"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading has-text-align-center" style="margin-top:0;margin-bottom:0;font-size:24px;font-weight:600;line-height:1.3"><?php esc_html_e( 'Log in to check out', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#757575"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-align-center has-text-color" style="color:#757575;margin-top:0;margin-bottom:0"><?php esc_html_e( 'You must be logged in to checkout.', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"top":{"color":"#dddddd","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"24px"}}},"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-group" style="border-top-color:#dddddd;border-top-style:solid;border-top-width:1px;padding-top:24px"><!-- wp:yaycheckout/login {"text":"<?php esc_html_e( 'Have an account? Log in to continue', 'yaycheckout' ); ?>","linkText":"<?php esc_html_e( 'Log in to continue', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"15px","fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/return-to-cart {"buttonText":"<?php esc_html_e( 'Return to cart', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"14px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/checkout -->
