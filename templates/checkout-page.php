<?php
/**
 * Checkout page: the layout's blocks in our own document, with no theme header
 * or footer, on every theme. The root Checkout block is the form; everything
 * WooCommerce needs on the page is a block. wp_head() loads the bundled
 * themeless theme's styles (ThemelessCheckout), or the site theme's when that
 * is turned off.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_layout  = \YayCheckout\Renderer\CheckoutPage::get_instance()->layout();
$yaycheckout_preview = \YayCheckout\Renderer\PreviewRequest::get_instance();

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

        // finalize() decides the blocks whose visibility depends on a block rendered after them.
        echo \YayCheckout\Renderer\BlockVisibility::get_instance()->finalize( do_blocks( $yaycheckout_layout->content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, rendered and escaped by each block.
    }
    ?>
</div>
<?php
$yaycheckout_main = (string) ob_get_clean();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    // Classic themes without title-tag print <title> in header.php, which no longer loads; block themes and title-tag themes get theirs from wp_head().
    if ( ! current_theme_supports( 'title-tag' ) && false === has_action( 'wp_head', '_block_template_render_title_tag' ) ) :
        ?>
        <title><?php echo esc_html( wp_get_document_title() ); ?></title>
    <?php endif; ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'yaycheckout-page' ); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php echo $yaycheckout_main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered above by the layout's blocks. ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
