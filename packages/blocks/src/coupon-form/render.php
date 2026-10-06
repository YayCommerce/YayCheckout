<?php
/**
 * Server render for yaycheckout/coupon-form.
 *
 * Storefront prints a coupon field and apply button, wired by assets/js/checkout-coupon-form.js.
 * Why no <form>: docs/architecture.md, Storefront (Coupon form).
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof WC_Cart || ! wc_coupons_enabled() ) {
    return;
}

// WooCommerce's own condition for its checkout coupon form: a guest who must log in gets no checkout form, so no coupon either.
if ( ! is_user_logged_in() && ! WC()->checkout()->is_registration_enabled() && WC()->checkout()->is_registration_required() ) {
    return;
}

$yaycheckout_placeholder = isset( $attributes['placeholder'] ) && is_string( $attributes['placeholder'] ) && '' !== $attributes['placeholder']
    ? $attributes['placeholder']
    : __( 'Coupon code', 'yaycheckout' );

$yaycheckout_button_text = isset( $attributes['buttonText'] ) && is_string( $attributes['buttonText'] ) && '' !== $attributes['buttonText']
    ? $attributes['buttonText']
    : __( 'Apply coupon', 'yaycheckout' );

$yaycheckout_button_class = 'button';

if ( function_exists( 'wc_wp_theme_get_element_class_name' ) && wc_wp_theme_get_element_class_name( 'button' ) ) {
    $yaycheckout_button_class .= ' ' . wc_wp_theme_get_element_class_name( 'button' );
}

wp_enqueue_script(
    'yaycheckout-coupon-form',
    YAYCHECKOUT_PLUGIN_URL . 'assets/js/checkout-coupon-form.js',
    [ 'jquery', 'wc-checkout' ],
    YAYCHECKOUT_VERSION,
    true
);

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-coupon-form' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <p class="form-row form-row-first">
        <label for="yaycheckout-coupon-code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'yaycheckout' ); ?></label>
        <?php // No name: a coupon_code posted with the checkout form would be noise in every update_order_review and order submit. ?>
        <input type="text" id="yaycheckout-coupon-code" class="input-text yaycheckout-coupon-form__code" placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>" value="" />
    </p>
    <p class="form-row form-row-last">
        <button type="button" class="<?php echo esc_attr( $yaycheckout_button_class ); ?> yaycheckout-coupon-form__apply"><?php echo esc_html( $yaycheckout_button_text ); ?></button>
    </p>
    <div class="clear"></div>
    <div class="yaycheckout-coupon-form__notices" aria-live="polite" data-error-text="<?php esc_attr_e( 'The coupon could not be applied. Please try again.', 'yaycheckout' ); ?>"></div>
</div>
