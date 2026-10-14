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
if ( \YayCheckout\Renderer\CheckoutLogin::must_log_in() ) {
    return;
}

$yaycheckout_placeholder = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'placeholder', __( 'Coupon code', 'yaycheckout' ) );

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

// Applied coupons count as the field's value, so their chips (and their ×) stay shown after a reload.
$yaycheckout_disclosure = \YayCheckout\Services\Disclosure::mode( $attributes, 'yaycheckout-coupon-code', false, WC()->cart->get_applied_coupons() );

?>
<div <?php echo \YayCheckout\Renderer\BlockEmptiness::wrapper( $yaycheckout_wrapper_attrs, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    echo \YayCheckout\Services\Disclosure::open_if( $attributes, 'yaycheckout-coupon-code', __( 'Coupon code', 'yaycheckout' ), $yaycheckout_disclosure ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- open() escapes.
    ?>
    <div class="yaycheckout-coupon-form__row">
        <span class="yaycheckout-coupon-form__field">
            <?php // Read by screen readers only, unless the layout's labels float: then it stands in for the placeholder (style.css). ?>
            <label for="yaycheckout-coupon-code" class="yaycheckout-coupon-form__label"><?php echo esc_html( $yaycheckout_placeholder ); ?></label>
            <?php
            // No name: a coupon_code posted with the checkout form would be noise in every update_order_review and order submit.
            // No input-text or form-row: inside the checkout form, WooCommerce's validate_field would claim the field and strip its error.
            ?>
            <input type="text" id="yaycheckout-coupon-code" class="yaycheckout-coupon-form__code" placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>" value="" />
        </span>
        <?php echo $yaycheckout_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered block markup; its render.php escapes. ?>
    </div>
    <?php echo \YayCheckout\Renderer\CheckoutFragments::coupon_chips_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by CheckoutFragments; update_order_review refreshes it. ?>
    <div class="yaycheckout-coupon-form__notices" aria-live="polite" data-error-text="<?php esc_attr_e( 'The coupon could not be applied. Please try again.', 'yaycheckout' ); ?>"></div>
    <?php echo \YayCheckout\Services\Disclosure::close_if( $yaycheckout_disclosure ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?>
</div>
