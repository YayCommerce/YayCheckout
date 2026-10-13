<?php
/**
 * Checkout page: the layout's blocks in our own document, with no theme header
 * or footer, on every theme. The root Checkout block is the form; everything
 * WooCommerce needs on the page is a block. The theme's styles still load
 * through wp_head(). CheckoutPage::page_template() rendered the layout already.
 */

defined( 'ABSPATH' ) || exit;
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
<?php echo \YayCheckout\Renderer\CheckoutPage::get_instance()->html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by the layout's blocks. ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
