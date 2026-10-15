<?php
/**
 * The default guest view every layout starts with: what a guest sees when WooCommerce gives them no
 * checkout form (guest checkout off and account creation during checkout off). It is the layout's
 * guest view post (Layout::GUEST_VIEW_POST_TYPE), not part of a template's markup.php: LayoutTemplateCatalogue::guest_view()
 * prints it, for a layout created from any template and for Reset.
 *
 * Keep the markup as the editor saves it, and use only blocks in Layout::GUEST_VIEW_BLOCKS. To change it,
 * build the blocks in the layout editor's Guest view, copy them from the Code editor and put back the PHP
 * (translated text).
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Log in to continue', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Log in to your account to complete your purchase.', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/login /-->

<!-- wp:yaycheckout/return-to-cart /-->
