<?php
/**
 * Prints the checkbox that splits billing and shipping, for WooCommerce's Shipping destination:
 * - billing is main: WooCommerce's own "Ship to a different address?" box from checkout/form-shipping.php,
 *   which checkout.js reads to show div.shipping_address (the Shipping block) and send its rates fields.
 *   With ticked "same", it prints "Use same address for shipping" instead, unnamed, and WooCommerce's box
 *   hidden with the opposite state, which checkout-different-address.js keeps in step;
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
        $yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::rich_text( $attributes, 'sameLabel', __( 'Use same address for billing', 'yaycheckout' ) );
        ?>
        <p class="form-row form-row-wide yaycheckout-different-address__same">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="yaycheckout-same-billing" <?php checked( ! \YayCheckout\Renderer\CheckoutDifferentAddress::saved_addresses_differ() ); ?> type="checkbox" name="<?php echo esc_attr( \YayCheckout\Renderer\CheckoutDifferentAddress::SAME_BILLING_FIELD ); ?>" value="1" /> <span><?php echo $yaycheckout_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered by wp_kses in LayoutMarkupReader::rich_text(). ?></span>
            </label>
        </p>
    <?php elseif ( 'same' === ( $attributes['ticked'] ?? 'different' ) ) : ?>
        <?php
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce filter; its default is 0 because billing is main here.
        $yaycheckout_ship_different = 1 === (int) apply_filters( 'woocommerce_ship_to_different_address_checked', 0 );
        $yaycheckout_label          = \YayCheckout\Services\LayoutMarkupReader::rich_text( $attributes, 'sameShipLabel', __( 'Use same address for shipping', 'yaycheckout' ) );
        ?>
        <p class="form-row form-row-wide yaycheckout-different-address__same">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="yaycheckout-same-shipping" <?php checked( ! $yaycheckout_ship_different ); ?> type="checkbox" value="1" data-summary-key="ship_to_different_address" /> <span><?php echo $yaycheckout_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered by wp_kses in LayoutMarkupReader::rich_text(). ?></span>
            </label>
        </p>
        <div id="ship-to-different-address" hidden>
            <input id="ship-to-different-address-checkbox" type="checkbox" name="ship_to_different_address" value="1" <?php checked( $yaycheckout_ship_different ); ?> />
        </div>
    <?php else : ?>
        <?php
        $yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::rich_text( $attributes, 'shipLabel', __( 'Ship to a different address?', 'yaycheckout' ) );
        ?>
        <div id="ship-to-different-address" class="form-row form-row-wide">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input id="ship-to-different-address-checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked( apply_filters( 'woocommerce_ship_to_different_address_checked', 0 ), 1 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce filter; its default is 0 because billing is main here. ?> type="checkbox" name="ship_to_different_address" value="1" /> <span><?php echo $yaycheckout_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtered by wp_kses in LayoutMarkupReader::rich_text(); the second sniff is for the WooCommerce filter earlier on this line, since only the last ignore on a line counts. ?></span>
            </label>
        </div>
    <?php endif; ?>
</div>
