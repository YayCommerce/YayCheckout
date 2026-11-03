<?php
/**
 * Prints WooCommerce's order_comments field; WC_Checkout saves what the shopper types as the
 * order's customer note.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; the block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// Without WooCommerce there is no checkout to post the note to.
if ( ! function_exists( 'woocommerce_form_field' ) ) {
    return;
}

$yaycheckout_label       = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'label', __( 'Order notes', 'yaycheckout' ) );
$yaycheckout_placeholder = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'placeholder', __( 'Notes about your order, e.g. special notes for delivery.', 'yaycheckout' ) );
$yaycheckout_description = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'description' );
$yaycheckout_required    = ! empty( $attributes['required'] );

$yaycheckout_key        = \YayCheckout\Services\LayoutMarkupReader::ORDER_NOTE_KEY;
$yaycheckout_value      = \YayCheckout\Renderer\CheckoutFieldDefaults::value( $yaycheckout_key );
$yaycheckout_disclosure = \YayCheckout\Services\Disclosure::mode( $attributes, $yaycheckout_key, $yaycheckout_required, $yaycheckout_value );

echo '<div ' . get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-note form-row' ] ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.

echo wp_kses( \YayCheckout\Services\Disclosure::open_if( $attributes, $yaycheckout_key, $yaycheckout_label, $yaycheckout_disclosure ), \YayCheckout\Services\Disclosure::ALLOWED_HTML );

woocommerce_form_field(
    $yaycheckout_key,
    [
        'type'        => 'textarea',
        'class'       => [ 'notes' ],
        'label'       => $yaycheckout_label,
        'required'    => $yaycheckout_required,
        'placeholder' => $yaycheckout_placeholder,
        // WooCommerce runs only wp_kses_post() on it, so a typed tag would render.
        'description' => esc_html( $yaycheckout_description ),
        'return'      => false,
    ],
    $yaycheckout_value
);

echo wp_kses( \YayCheckout\Services\Disclosure::close_if( $yaycheckout_disclosure ), \YayCheckout\Services\Disclosure::ALLOWED_HTML ) . '</div>';
