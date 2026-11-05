<?php
/**
 * Pro's thank-you page (Pro\Renderer\ThankYouPage::page_template()): the layout's blocks in our own document, with no theme header
 * or footer, on every theme. After the markup, WooCommerce's thank-you hooks
 * fire so gateways can print payment instructions, unless the layout's Payment
 * Instructions block already printed them in place. wp_head() loads the bundled
 * themeless theme's styles (ThemelessCheckout), or the site theme's when that
 * is turned off.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_thank_you = \YayCheckout\Pro\Renderer\ThankYouPage::get_instance();
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
    $yaycheckout_preview = \YayCheckout\Renderer\PreviewRequest::get_instance();

    if ( null !== $yaycheckout_layout && null !== $yaycheckout_preview->layout_id() ) {
        $yaycheckout_preview->banner( $yaycheckout_layout );
    }

    if ( null !== $yaycheckout_layout ) {
        // finalize() decides the blocks whose visibility depends on a block rendered after them.
        echo \YayCheckout\Renderer\BlockVisibility::get_instance()->finalize( \YayCheckout\Renderer\LayoutShortcodes::render( $yaycheckout_layout->content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, rendered and escaped by each block.
    }

    if ( null !== $yaycheckout_order ) {
        $yaycheckout_order_id = $yaycheckout_order->get_id();

        // When the Save as PDF Button printed a payment slot above, a wrapper lets it copy the gateway's output (bank details)
        // without running the gateway's hook again. Otherwise the output stays unwrapped, as WooCommerce prints it. The Payment
        // Instructions block carries the wrapper's class itself, so the copy comes from it when the layout has one.
        $yaycheckout_wrap_gateway = \YayCheckout\Pro\Renderer\OrderPrintSummary::has_payment_slot();

        // Same order as WooCommerce's checkout/thankyou.php so gateway instructions still print.
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce thank-you hooks.
        do_action( 'woocommerce_before_thankyou', $yaycheckout_order_id );
        // Not woocommerce_thankyou: WooCommerce prints its order table and addresses on it, which the Order Details and Order Addresses blocks already place.
        // phpcs:enable

        // woocommerce_thankyou_<gateway>, unless the Payment Instructions block already fired it in the layout. Gateways and tracking scripts on it still run without the block.
        $yaycheckout_gateway_output = $yaycheckout_thank_you->payment_instructions();

        if ( $yaycheckout_wrap_gateway && '' !== $yaycheckout_gateway_output ) {
            echo '<div class="' . esc_attr( \YayCheckout\Pro\Renderer\OrderPrintSummary::GATEWAY_CLASS ) . '">';
        }

        echo $yaycheckout_gateway_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Whatever the gateway's callbacks print, as do_action() would.

        if ( $yaycheckout_wrap_gateway && '' !== $yaycheckout_gateway_output ) {
            echo '</div>';
        }
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
