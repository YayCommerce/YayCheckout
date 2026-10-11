<?php
/**
 * Prints div.yaycheckout-shipping: an optional heading and its inner Field blocks.
 * woocommerce-shipping-fields is the wrapper country-select.js searches for
 * #shipping_state; without it the state box never repopulates.
 * shipping_address is the class WooCommerce's checkout.js shows and hides with the
 * ship_to_different_address box, and reads the rates fields from while it is checked.
 * WooCommerce's box is printed here, checked and hidden, when nothing else decides it;
 * CheckoutDifferentAddress::render_plan() says when.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! \YayCheckout\Renderer\CheckoutDifferentAddress::needs_shipping_address() ) {
    return;
}

$yaycheckout_title      = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? $attributes['title'] : __( 'Shipping', 'yaycheckout' );
$yaycheckout_show_title = ! isset( $attributes['showTitle'] ) || (bool) $attributes['showTitle'];
$yaycheckout_plan       = \YayCheckout\Renderer\CheckoutDifferentAddress::render_plan( \YayCheckout\Renderer\CheckoutPage::get_instance()->layout() );

// Block spacing. Two classes, to beat the stylesheet's default.
$yaycheckout_class     = 'yaycheckout-shipping woocommerce-shipping-fields shipping_address';
$yaycheckout_gap_class = wp_unique_id( 'yaycheckout-shipping-gap-' );

if ( \YayCheckout\Renderer\BlockGap::add_rules( $attributes['style'] ?? [], 'yaycheckout/shipping', '.yaycheckout-shipping.' . $yaycheckout_gap_class ) ) {
    $yaycheckout_class .= ' ' . $yaycheckout_gap_class;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => $yaycheckout_class ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_plan['wc_box'] ) : ?>
        <div id="ship-to-different-address" hidden>
            <input id="ship-to-different-address-checkbox" type="checkbox" name="ship_to_different_address" value="1" checked />
        </div>
    <?php endif; ?>
    <?php if ( $yaycheckout_plan['same_marker'] ) : ?>
        <input type="hidden" name="<?php echo esc_attr( \YayCheckout\Renderer\CheckoutDifferentAddress::SAME_BILLING_FIELD ); ?>" value="1" />
    <?php endif; ?>
    <?php if ( $yaycheckout_show_title ) : ?>
        <h3><?php echo esc_html( $yaycheckout_title ); ?></h3>
        <?php endif; ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
