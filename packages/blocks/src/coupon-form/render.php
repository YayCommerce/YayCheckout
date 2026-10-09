<?php
/**
 * Server render for yaycheckout/coupon-form.
 *
 * Storefront prints a coupon field and apply button, wired by assets/js/checkout-coupon-form.js,
 * which block.json's viewScript loads (handle registered in BlockRegistry::register_view_scripts()).
 * Why no <form>: docs/architecture.md, Storefront (Coupon form).
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    The Coupon Button inner block's markup; empty when the form has none.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof WC_Cart || ! wc_coupons_enabled() ) {
    return;
}

// Mirrors woocommerce_checkout_coupon_form(): a guest who must log in gets no checkout form, so no coupon either.
$yaycheckout_checkout = WC()->checkout();

if ( ! is_user_logged_in() && ! $yaycheckout_checkout->is_registration_enabled() && $yaycheckout_checkout->is_registration_required() ) {
    return;
}

$yaycheckout_placeholder = isset( $attributes['placeholder'] ) && is_string( $attributes['placeholder'] ) && '' !== $attributes['placeholder']
    ? $attributes['placeholder']
    : __( 'Coupon code', 'yaycheckout' );

// The Button colors are the Coupon Button's starting look; its own colour support wins (coupon-button/style.css).
$yaycheckout_button_vars       = [];
$yaycheckout_button_properties = [
    'buttonBackgroundColor' => '--yaycheckout-coupon-button-background',
    'buttonTextColor'       => '--yaycheckout-coupon-button-text',
];

foreach ( $yaycheckout_button_properties as $yaycheckout_attr => $yaycheckout_property ) {
    $yaycheckout_color = isset( $attributes[ $yaycheckout_attr ] ) && is_string( $attributes[ $yaycheckout_attr ] ) ? $attributes[ $yaycheckout_attr ] : '';

    // Kept only if core's CSS filter passes it whole as a color, so no second declaration rides along.
    if ( '' !== $yaycheckout_color && 'color:' . $yaycheckout_color === safecss_filter_attr( 'color:' . $yaycheckout_color ) ) {
        $yaycheckout_button_vars[] = $yaycheckout_property . ':' . $yaycheckout_color;
    }
}

$yaycheckout_wrapper_attrs = [ 'class' => 'yaycheckout-coupon-form' ];

if ( $yaycheckout_button_vars ) {
    $yaycheckout_wrapper_attrs['style'] = implode( ';', $yaycheckout_button_vars ) . ';';
}

// Inner block markup (the Coupon Button); a form saved without one gets the default button.
$yaycheckout_button = '' !== trim( $content )
    ? $content
    : render_block(
        [
            'blockName'    => 'yaycheckout/coupon-button',
            'attrs'        => [],
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ]
    );

$yaycheckout_disclosure = \YayCheckout\Services\Disclosure::mode( $attributes, 'yaycheckout-coupon-code', false );

?>
<div <?php echo \YayCheckout\Renderer\HideWhenEmpty::wrapper( $yaycheckout_wrapper_attrs, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    if ( \YayCheckout\Services\Disclosure::NONE !== $yaycheckout_disclosure ) {
        echo \YayCheckout\Services\Disclosure::open( 'yaycheckout-coupon-code', \YayCheckout\Services\Disclosure::text( $attributes, 'yaycheckout-coupon-code', __( 'Coupon code', 'yaycheckout' ), $yaycheckout_disclosure ), $yaycheckout_disclosure ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- open() escapes.
    }
    ?>
    <div class="yaycheckout-coupon-form__row">
        <label for="yaycheckout-coupon-code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'yaycheckout' ); ?></label>
        <?php
        // No name: a coupon_code posted with the checkout form would be noise in every update_order_review and order submit.
        // No input-text or form-row: inside the checkout form, WooCommerce's validate_field would claim the field and strip its error.
        ?>
        <input type="text" id="yaycheckout-coupon-code" class="yaycheckout-coupon-form__code" placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>" value="" />
        <?php echo $yaycheckout_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered block markup; its render.php escapes. ?>
    </div>
    <div class="yaycheckout-coupon-form__notices" aria-live="polite" data-error-text="<?php esc_attr_e( 'The coupon could not be applied. Please try again.', 'yaycheckout' ); ?>"></div>
    <?php echo \YayCheckout\Services\Disclosure::NONE !== $yaycheckout_disclosure ? \YayCheckout\Services\Disclosure::close() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?>
</div>
