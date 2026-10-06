<?php
/**
 * Prints WooCommerce's terms, Place order button and checkout nonce; without a
 * cart, only the disabled button. The button is the block's root element, so
 * its style supports land on it.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_has_cart      = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;
$yaycheckout_default_label = __( 'Place order', 'yaycheckout' );
$yaycheckout_label         = trim( html_entity_decode( wp_strip_all_tags( \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'buttonText' ), true ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );

if ( '' === $yaycheckout_label || $yaycheckout_default_label === $yaycheckout_label ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
    $yaycheckout_label = (string) apply_filters( 'woocommerce_order_button_text', $yaycheckout_default_label );
}

$yaycheckout_button = '<button type="submit" '
    . get_block_wrapper_attributes( [ 'class' => 'yaycheckout-place-order wp-element-button' ] )
    . ' name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $yaycheckout_label ) . '" data-value="' . esc_attr( $yaycheckout_label ) . '"'
    . ( $yaycheckout_has_cart ? '' : ' disabled' )
    . '>' . esc_html( $yaycheckout_label ) . '</button>';

if ( ! $yaycheckout_has_cart ) {
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
