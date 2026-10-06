<?php
/**
 * Prints WooCommerce's terms, Place order button and checkout nonce; without a
 * cart, a disabled button. The button is the inner core/button, which gets
 * Woo's id, name and value here. A layout saved before the inner button
 * existed, or one whose button text is empty (core then renders nothing),
 * gets the legacy button instead.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    The rendered core/buttons > core/button.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_has_cart      = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;
$yaycheckout_default_label = __( 'Place order', 'yaycheckout' );
$yaycheckout_content       = $content;
$yaycheckout_label         = trim( html_entity_decode( wp_strip_all_tags( $yaycheckout_content ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );

// Only the core button's text is in the content, so the default label's first occurrence is that text.
if ( $yaycheckout_default_label === $yaycheckout_label ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
    $yaycheckout_label   = (string) apply_filters( 'woocommerce_order_button_text', $yaycheckout_label );
    $yaycheckout_content = substr_replace( $yaycheckout_content, esc_html( $yaycheckout_label ), strpos( $yaycheckout_content, esc_html( $yaycheckout_default_label ) ), strlen( esc_html( $yaycheckout_default_label ) ) );
}

$yaycheckout_button = new WP_HTML_Tag_Processor( $yaycheckout_content );

if ( $yaycheckout_button->next_tag(
    [
        'tag_name'   => 'BUTTON',
        'class_name' => 'wp-block-button__link',
    ]
) ) {
    $yaycheckout_button->set_attribute( 'id', 'place_order' );
    $yaycheckout_button->set_attribute( 'name', 'woocommerce_checkout_place_order' );
    $yaycheckout_button->set_attribute( 'value', $yaycheckout_label );
    $yaycheckout_button->set_attribute( 'data-value', $yaycheckout_label );

    if ( ! $yaycheckout_has_cart ) {
        $yaycheckout_button->set_attribute( 'disabled', true );
    }

    $yaycheckout_button_html = $yaycheckout_button->get_updated_html();
} else {
    $yaycheckout_button_text = isset( $attributes['buttonText'] ) && is_string( $attributes['buttonText'] )
        ? $attributes['buttonText']
        : '';
    $yaycheckout_label       = '' !== $yaycheckout_button_text ? $yaycheckout_button_text : $yaycheckout_default_label;

    if ( ! $yaycheckout_has_cart ) {
        $yaycheckout_button_html = '<button type="button" class="button alt" disabled>' . esc_html( $yaycheckout_label ) . '</button>';
    } else {
        $yaycheckout_button_class = 'button alt';

        if ( function_exists( 'wc_wp_theme_get_element_class_name' ) && wc_wp_theme_get_element_class_name( 'button' ) ) {
            $yaycheckout_button_class .= ' ' . wc_wp_theme_get_element_class_name( 'button' );
        }

        if ( '' === $yaycheckout_button_text ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            $yaycheckout_label = apply_filters( 'woocommerce_order_button_text', $yaycheckout_label );
        }

        $yaycheckout_button_html = '<button type="submit" class="' . esc_attr( $yaycheckout_button_class ) . '" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $yaycheckout_label ) . '" data-value="' . esc_attr( $yaycheckout_label ) . '">' . esc_html( $yaycheckout_label ) . '</button>';
    }
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-place-order' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( ! $yaycheckout_has_cart ) : ?>
        <?php echo $yaycheckout_button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-rendered block HTML, or the label escaped above. ?>
    <?php else : ?>
        <div class="form-row place-order">
            <?php
            if ( function_exists( 'wc_get_template' ) ) {
                wc_get_template( 'checkout/terms.php' );
            }

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            do_action( 'woocommerce_review_order_before_submit' );

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's button filter; the HTML is core-rendered or escaped above.
            echo apply_filters(
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
                'woocommerce_order_button_html',
                $yaycheckout_button_html
            );

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            do_action( 'woocommerce_review_order_after_submit' );

            wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
            ?>
        </div>
    <?php endif; ?>
</div>
