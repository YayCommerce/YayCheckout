<?php
/**
 * Prints WooCommerce's terms, Place order button and checkout nonce; without a
 * cart, a disabled button.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_button_text = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'buttonText' );

$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;
$yaycheckout_label    = '' !== $yaycheckout_button_text ? $yaycheckout_button_text : __( 'Place order', 'yaycheckout' );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-place-order' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( ! $yaycheckout_has_cart ) : ?>
        <button type="button" class="button alt" disabled><?php echo esc_html( $yaycheckout_label ); ?></button>
    <?php else : ?>
        <?php
        $yaycheckout_button_class = 'button alt';

        if ( function_exists( 'wc_wp_theme_get_element_class_name' ) && wc_wp_theme_get_element_class_name( 'button' ) ) {
            $yaycheckout_button_class .= ' ' . wc_wp_theme_get_element_class_name( 'button' );
        }

        if ( '' === $yaycheckout_button_text ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            $yaycheckout_button_text = apply_filters( 'woocommerce_order_button_text', $yaycheckout_label );
            $yaycheckout_label       = $yaycheckout_button_text;
        }
        ?>
        <div class="form-row place-order">
            <?php
            if ( function_exists( 'wc_get_template' ) ) {
                wc_get_template( 'checkout/terms.php' );
            }

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            do_action( 'woocommerce_review_order_before_submit' );

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's button filter; class/value/label are escaped above.
            echo apply_filters(
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
                'woocommerce_order_button_html',
                '<button type="submit" class="' . esc_attr( $yaycheckout_button_class ) . '" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $yaycheckout_label ) . '" data-value="' . esc_attr( $yaycheckout_label ) . '">' . esc_html( $yaycheckout_label ) . '</button>'
            );

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            do_action( 'woocommerce_review_order_after_submit' );

            wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
            ?>
        </div>
    <?php endif; ?>
</div>
