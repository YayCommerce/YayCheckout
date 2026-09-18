<?php
/**
 * Server render for yaycheckout/shipping.
 *
 * Inner Field blocks supply the address controls. Attributes are read with
 * defaults so markup saved before an attribute existed still renders.
 *
 * Prints WooCommerce's "Ship to a different address?" checkbox and the
 * div.shipping_address wrapper so checkout.js toggles the fields and
 * WC_Checkout reads them exactly as on the stock form. Nothing renders when the
 * cart needs no shipping address or the store ships to the billing address only.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_title      = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? $attributes['title'] : __( 'Shipping', 'yaycheckout' );
$yaycheckout_show_title = ! isset( $attributes['showTitle'] ) || (bool) $attributes['showTitle'];

// No cart outside the storefront (admin, REST, CLI): render the fields plainly so the editor preview still works.
$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;

if ( $yaycheckout_has_cart && ( ! WC()->cart->needs_shipping_address() || wc_ship_to_billing_address_only() ) ) {
    return;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-shipping woocommerce-shipping-fields' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_has_cart ) : ?>
        <h3 id="ship-to-different-address">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Verbatim copy of WooCommerce's form-shipping.php so its filter and translations still apply. ?>
                <input id="ship-to-different-address-checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked( apply_filters( 'woocommerce_ship_to_different_address_checked', 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ? 1 : 0 ), 1 ); ?> type="checkbox" name="ship_to_different_address" value="1" /> <span><?php esc_html_e( 'Ship to a different address?', 'woocommerce' ); ?></span>
            </label>
        </h3>
    <?php endif; ?>
    <div class="shipping_address">
        <?php if ( $yaycheckout_show_title ) : ?>
            <h3><?php echo esc_html( $yaycheckout_title ); ?></h3>
        <?php endif; ?>
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
    </div>
</div>
