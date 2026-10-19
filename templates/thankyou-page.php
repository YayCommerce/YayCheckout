<?php
/**
 * Thank-you page: the layout's blocks in our own document, with no theme header
 * or footer, on every theme. After the markup, WooCommerce's thank-you hooks
 * fire so gateways can print payment instructions. wp_head() loads the bundled
 * themeless theme's styles (ThemelessCheckout), or the site theme's when that
 * is turned off.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_thank_you = \YayCheckout\Renderer\ThankYouPage::get_instance();
$yaycheckout_layout    = $yaycheckout_thank_you->layout();
$yaycheckout_order     = $yaycheckout_thank_you->order();

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
    if ( null !== $yaycheckout_layout ) {
        // finalize() decides the blocks whose visibility depends on a block rendered after them.
        echo \YayCheckout\Renderer\BlockVisibility::get_instance()->finalize( \YayCheckout\Renderer\LayoutShortcodes::render( $yaycheckout_layout->content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, rendered and escaped by each block.
    }

    if ( null !== $yaycheckout_order ) {
        $yaycheckout_order_id = $yaycheckout_order->get_id();

        // Same order as WooCommerce's checkout/thankyou.php so gateway instructions still print.
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce thank-you hooks.
        do_action( 'woocommerce_before_thankyou', $yaycheckout_order_id );
        do_action( 'woocommerce_thankyou_' . $yaycheckout_order->get_payment_method(), $yaycheckout_order_id );
        // do_action( 'woocommerce_thankyou', $yaycheckout_order_id );
        // phpcs:enable
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
