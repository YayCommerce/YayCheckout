<?php
/**
 * Prints div.yaycheckout-shipping: an optional heading and its inner Field blocks.
 * woocommerce-shipping-fields is the wrapper country-select.js searches for
 * #shipping_state; without it the state box never repopulates.
 * shipping_address is the class WooCommerce's checkout.js shows and hides with the
 * ship_to_different_address box, and reads the rates fields from while it is checked.
 * WooCommerce's box is printed here, checked and hidden, when nothing else decides it:
 * shipping is the main address, or the layout has no Different address block.
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
$yaycheckout_layout     = \YayCheckout\Renderer\CheckoutPage::get_instance()->layout();
$yaycheckout_main       = 'shipping' === \YayCheckout\Renderer\CheckoutDifferentAddress::destination();
$yaycheckout_always     = null !== $yaycheckout_layout && ( $yaycheckout_main || ! \YayCheckout\Services\LayoutMarkupReader::has_different_address( $yaycheckout_layout->content ) );
// Shipping is main and there is no Billing section: billing is always the shipping address.
$yaycheckout_copy = null !== $yaycheckout_layout && $yaycheckout_main && ! \YayCheckout\Services\LayoutMarkupReader::has_billing( $yaycheckout_layout->content );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-shipping woocommerce-shipping-fields shipping_address' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_always ) : ?>
        <div id="ship-to-different-address" hidden>
            <input id="ship-to-different-address-checkbox" type="checkbox" name="ship_to_different_address" value="1" checked />
        </div>
    <?php endif; ?>
    <?php if ( $yaycheckout_copy ) : ?>
        <input type="hidden" name="<?php echo esc_attr( \YayCheckout\Renderer\CheckoutDifferentAddress::SAME_BILLING_FIELD ); ?>" value="1" />
    <?php endif; ?>
    <?php if ( $yaycheckout_show_title ) : ?>
        <h3><?php echo esc_html( $yaycheckout_title ); ?></h3>
        <?php endif; ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
