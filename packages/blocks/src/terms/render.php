<?php
/**
 * Prints WooCommerce's checkout terms: the privacy text and, when a Terms page is set, the
 * required terms checkbox. Place Order no longer prints them, so a layout without this block
 * has neither. Prints nothing without a cart, or when WooCommerce has nothing to show.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof WC_Cart || ! function_exists( 'wc_get_template' ) ) {
    return;
}

// Buffered so the merchant's padding, border or background don't draw an empty box.
ob_start();
// A WooCommerce Hook block placed for the before or after hook prints it instead.
\YayCheckout\Renderer\WcHooks::without_placed(
    [ 'woocommerce_checkout_before_terms_and_conditions', 'woocommerce_checkout_after_terms_and_conditions' ],
    static function () {
        wc_get_template( 'checkout/terms.php' );
    }
);
$yaycheckout_terms = (string) ob_get_clean();

if ( '' === trim( wp_strip_all_tags( $yaycheckout_terms ) ) ) {
    return;
}

$yaycheckout_wrapper = \YayCheckout\Renderer\BlockEmptiness::wrapper( [ 'class' => 'yaycheckout-terms' ], $attributes );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $yaycheckout_terms; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's template, escaped there. ?>
</div>
