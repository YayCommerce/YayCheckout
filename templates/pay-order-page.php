<?php
/**
 * Pay-order page: our own document, with no theme header or footer, on every theme. Its body is
 * WooCommerce's [woocommerce_checkout], so order_pay() checks the order and the shopper and prints its
 * own notices; when it lets them pay, its checkout/form-pay.php is ours (templates/checkout-form-pay.php),
 * which prints the layout's blocks. wp_head() loads the bundled themeless theme's styles (ThemelessCheckout),
 * or the site theme's when that is turned off.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Rendered before wp_head(), as core's template-canvas.php does: blocks enqueue
 * script modules and styles while rendering, and on block themes the import
 * map is printed in the head. Rendered after it, a module's imports go unmapped
 * and the browser refuses to load it.
 */
ob_start();
?>
<div class="woocommerce yaycheckout-page">
    <?php echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's shortcode output and the layout's blocks, escaped there. ?>
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
<?php echo $yaycheckout_main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered above. ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
