<?php
/**
 * Prints the checkbox that splits billing and shipping, for WooCommerce's Shipping destination:
 * - billing is main: WooCommerce's own "Ship to a different address?" box from checkout/form-shipping.php,
 *   which checkout.js reads to show div.shipping_address (the Shipping block) and send its rates fields;
 * - shipping is main: "Use same address for billing", posted as yaycheckout_same_billing. The Shipping block
 *   prints WooCommerce's box checked and hidden, and CheckoutDifferentAddress copies shipping into billing.
 * Prints nothing when shipping is forced to billing, the cart needs no shipping address, the layout has no
 * Shipping section, or shipping is main and there is no Billing section to replace.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; the block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_block = \YayCheckout\Renderer\CheckoutDifferentAddress::render_plan( \YayCheckout\Renderer\CheckoutPage::get_instance()->layout() )['block'];

if ( '' === $yaycheckout_block ) {
    return;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-different-address' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( 'same' === $yaycheckout_block ) : ?>
        <?php
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Verbatim WooCommerce string so the storefront matches WooCommerce's Checkout block when the merchant leaves the label empty.
        $yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'sameLabel', __( 'Use same address for billing', 'woocommerce' ) );
        ?>
        <p class="form-row form-row-wide yaycheckout-different-address__same">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="yaycheckout-same-billing" <?php checked( ! \YayCheckout\Renderer\CheckoutDifferentAddress::saved_addresses_differ() ); ?> type="checkbox" name="<?php echo esc_attr( \YayCheckout\Renderer\CheckoutDifferentAddress::SAME_BILLING_FIELD ); ?>" value="1" /> <span><?php echo esc_html( $yaycheckout_label ); ?></span>
            </label>
        </p>
    <?php else : ?>
        <?php
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Verbatim WooCommerce string so the storefront matches classic checkout when the merchant leaves the label empty.
        $yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'shipLabel', __( 'Ship to a different address?', 'woocommerce' ) );
        ?>
        <div id="ship-to-different-address" class="form-row form-row-wide">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input id="ship-to-different-address-checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked( apply_filters( 'woocommerce_ship_to_different_address_checked', 0 ), 1 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce filter; its default is 0 because billing is main here. ?> type="checkbox" name="ship_to_different_address" value="1" /> <span><?php echo esc_html( $yaycheckout_label ); ?></span>
            </label>
        </div>
    <?php endif; ?>
</div>
