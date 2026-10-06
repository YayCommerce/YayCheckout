<?php
/**
 * Checkout page: the theme's header and footer around the layout's blocks. The
 * root Checkout block is the form; everything WooCommerce needs on the page is a block.
 * Classic themes print the whole document from header.php/footer.php; block
 * themes have no such files, so the shell is ours and the header/footer are
 * the theme's template parts.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_layout   = \YayCheckout\Renderer\CheckoutPage::get_instance()->layout();
$yaycheckout_preview  = \YayCheckout\Renderer\PreviewRequest::get_instance();
$yaycheckout_is_block = wp_is_block_theme();

/*
 * Rendered before wp_head(), as core's template-canvas.php does: blocks enqueue
 * script modules and styles while rendering, and on block themes the import
 * map is printed in the head. Rendered after it, a module's imports go unmapped
 * and the browser refuses to load it.
 */
ob_start();
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
$yaycheckout_main = (string) ob_get_clean();

if ( ! $yaycheckout_is_block ) {
    get_header();
    echo $yaycheckout_main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered above.
    get_footer();
    return;
}

ob_start();
block_header_area();
$yaycheckout_header = (string) ob_get_clean();

ob_start();
block_footer_area();
$yaycheckout_footer = (string) ob_get_clean();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'yaycheckout-page' ); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered above by core and the layout's blocks.
echo $yaycheckout_header . $yaycheckout_main . $yaycheckout_footer;
?>
</div>
<?php wp_footer(); ?>
</body>
</html>
