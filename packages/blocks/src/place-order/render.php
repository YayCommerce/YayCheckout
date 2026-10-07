<?php
/**
 * Prints WooCommerce's terms, Place order button and checkout nonce; without a
 * cart, only the disabled button. The button is the block's root element, so
 * its style supports land on it. During update_order_review only the button is
 * printed, as the fragment that refreshes its order total (CheckoutFragments).
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_has_cart      = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;
$yaycheckout_default_label = __( 'Place order', 'yaycheckout' );
$yaycheckout_label         = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText' );
$yaycheckout_show_total    = isset( $attributes['showTotal'] ) && $attributes['showTotal'];

if ( '' === $yaycheckout_label || $yaycheckout_default_label === $yaycheckout_label ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
    $yaycheckout_label = (string) apply_filters( 'woocommerce_order_button_text', $yaycheckout_default_label );
}

$yaycheckout_wrapper = [
    'class' => 'yaycheckout-place-order wp-element-button',
];

// The total is shown by ::after, which also lands in the button's accessible name.
if ( $yaycheckout_show_total && $yaycheckout_has_cart ) {
    $yaycheckout_wrapper['data-show-total'] = '1';
    $yaycheckout_wrapper['data-total']      = wp_strip_all_tags( wc_price( WC()->cart->get_total( 'edit' ) ) );
}

$yaycheckout_button = '<button type="submit" '
    . get_block_wrapper_attributes( $yaycheckout_wrapper )
    . ' name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $yaycheckout_label ) . '" data-value="' . esc_attr( $yaycheckout_label ) . '"'
    . ( $yaycheckout_has_cart ? '' : ' disabled' )
    . '>' . esc_html( $yaycheckout_label ) . '</button>';

// Unfiltered in the fragment: woocommerce_order_button_html may add siblings, which replacing #place_order would repeat.
if ( ! $yaycheckout_has_cart || wp_doing_ajax() ) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the wrapper attributes; the label is escaped above.
    echo $yaycheckout_button;
    return;
}
?>
<div class="form-row place-order">
    <?php
    if ( function_exists( 'wc_get_template' ) ) {
        wc_get_template( 'checkout/terms.php' );
    }

    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
    do_action( 'woocommerce_review_order_before_submit' );

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's button filter; core escapes the wrapper attributes and the label is escaped above.
    echo apply_filters(
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
        'woocommerce_order_button_html',
        $yaycheckout_button
    );

    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
    do_action( 'woocommerce_review_order_after_submit' );

    wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
    ?>
</div>
