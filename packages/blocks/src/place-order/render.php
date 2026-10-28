<?php
/**
 * Prints the Place order button, WooCommerce's submit hooks and checkout nonce;
 * without a cart, only the disabled button. On Pro's pay-order page, the Pay for order button
 * with WooCommerce's form-pay hooks instead. The terms and privacy text are the
 * Terms block's (terms/render.php), so they never widen the button's row. The
 * button is the block's root element, so its style supports land on it. Its label may hold live values, filled here so
 * value and data-value carry their text; live-value/view.ts keeps the spans
 * when WooCommerce resets the label to data-value.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// Pro's pay-order page (PayOrderForm): never in Free.
$yaycheckout_pay_form      = \YayCheckout\Renderer\PayOrderForm::current();
$yaycheckout_on_pay_form   = null !== $yaycheckout_pay_form;
$yaycheckout_has_cart      = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;
$yaycheckout_default_label = __( 'Place order', 'yaycheckout' );
$yaycheckout_plain_label   = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText' );

if ( '' === $yaycheckout_plain_label || $yaycheckout_default_label === $yaycheckout_plain_label ) {
    // On the pay-order page, WooCommerce's pay text (woocommerce_pay_order_button_text) as form-pay.php gets it.
    $yaycheckout_label_html = $yaycheckout_on_pay_form
        ? esc_html( $yaycheckout_pay_form['button_text'] )
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
        : esc_html( (string) apply_filters( 'woocommerce_order_button_text', $yaycheckout_default_label ) );
} else {
    $yaycheckout_label_html = \YayCheckout\Renderer\LiveValues::get_instance()->fill_html(
        \YayCheckout\Services\LayoutMarkupReader::rich_text( $attributes, 'buttonText' )
    );
}

$yaycheckout_label = trim( html_entity_decode( wp_strip_all_tags( $yaycheckout_label_html ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );

$yaycheckout_button = '<button type="submit" '
    . get_block_wrapper_attributes( [ 'class' => 'yaycheckout-place-order wp-element-button' . ( empty( $attributes['spread'] ) ? '' : ' yaycheckout-place-order--spread' ) ] )
    . ( $yaycheckout_on_pay_form ? '' : ' name="woocommerce_checkout_place_order"' )
    . ' id="place_order" value="' . esc_attr( $yaycheckout_label ) . '" data-value="' . esc_attr( $yaycheckout_label ) . '"'
    . ( $yaycheckout_has_cart || $yaycheckout_on_pay_form ? '' : ' disabled' )
    . '>' . $yaycheckout_label_html . '</button>';

// The pay-order form: WooCommerce's pay hooks and button filter; Pro's checkout-form-pay.php prints woocommerce_pay and its nonce.
if ( $yaycheckout_on_pay_form ) {
    ?>
<div class="form-row place-order">
    <?php
    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's form-pay hooks.
    do_action( 'woocommerce_pay_order_before_submit' );

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's button filter; core escapes the wrapper attributes and the label is escaped or kses-filtered above.
    echo apply_filters( 'woocommerce_pay_order_button_html', $yaycheckout_button );

    do_action( 'woocommerce_pay_order_after_submit' );
    // phpcs:enable
    ?>
</div>
    <?php
    return;
}

if ( ! $yaycheckout_has_cart ) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the wrapper attributes; the label is escaped or kses-filtered above.
    echo $yaycheckout_button;
    return;
}
?>
<div class="form-row place-order">
    <?php
    \YayCheckout\Renderer\WcHooks::in_place( 'woocommerce_review_order_before_submit' );

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's button filter; core escapes the wrapper attributes and the label is escaped or kses-filtered above.
    echo apply_filters(
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
        'woocommerce_order_button_html',
        $yaycheckout_button
    );

    \YayCheckout\Renderer\WcHooks::in_place( 'woocommerce_review_order_after_submit' );

    wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
    ?>
</div>
