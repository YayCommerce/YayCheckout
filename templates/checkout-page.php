<?php
/**
 * Checkout page: the theme's header and footer around the layout's blocks.
 * The root Checkout block is the form; everything WooCommerce needs on the
 * page is a block.
 *
 * Classic themes print the whole document from header.php/footer.php; block
 * themes have no such files, so the shell is ours and the header/footer are
 * the theme's template parts.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_layout   = \YayCheckout\Renderer\CheckoutPage::get_instance()->layout();
$yaycheckout_preview  = \YayCheckout\Renderer\PreviewRequest::get_instance();
$yaycheckout_is_block = wp_is_block_theme();

if ( $yaycheckout_is_block ) :
    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
    </head>
    <body <?php body_class( 'yaycheckout-page' ); ?>>
    <?php
    wp_body_open();
    echo '<div class="wp-site-blocks">';
    block_header_area();
else :
    get_header();
endif;
?>
<div class="woocommerce yaycheckout-page">
    <?php
    if ( null !== $yaycheckout_layout && $yaycheckout_preview->active() ) {
        $yaycheckout_preview->banner( $yaycheckout_layout );
    }

    if ( null !== $yaycheckout_layout ) {
        // Shipping packages only exist after a calculation; session totals alone leave the order table without shipping rows.
        WC()->cart->calculate_totals();

        echo do_blocks( $yaycheckout_layout->content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, rendered and escaped by each block.
    }
    ?>
</div>
<?php
if ( $yaycheckout_is_block ) {
    block_footer_area();
    echo '</div>';
    wp_footer();
    echo '</body></html>';
} else {
    get_footer();
}
