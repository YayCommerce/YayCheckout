<?php
/**
 * Prints one checkout field's value as the shopper last saved it, or the placeholder, muted, while
 * it is empty. view.js keeps it in step with the form as they type.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; the block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_key = isset( $attributes['fieldKey'] ) && is_string( $attributes['fieldKey'] ) ? $attributes['fieldKey'] : '';

// Only address and email keys; any other key could read a value this block was never meant to show.
if ( ! preg_match( '/^(billing|shipping)_[a-z0-9_]+$/', $yaycheckout_key, $yaycheckout_match ) ) {
    return;
}

$yaycheckout_prefix = $yaycheckout_match[1];

// Without a ship_to_different_address checkbox WooCommerce copies billing into shipping, so an
// empty shipping value reads its billing twin.
$yaycheckout_read = static function ( string $key ): string {
    foreach ( array_unique( [ $key, preg_replace( '/^shipping_/', 'billing_', $key ) ] ) as $candidate ) {
        $value = \YayCheckout\Renderer\CheckoutFieldDefaults::value( $candidate );
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';
        if ( '' !== $value ) {
            return $value;
        }
    }

    return '';
};

$yaycheckout_value = $yaycheckout_read( $yaycheckout_key );
$yaycheckout_names = [];

// Country and state show by name, as the selects do. The names carry entities, which esc_html() keeps.
if ( '' !== $yaycheckout_value && function_exists( 'WC' ) && WC()->countries instanceof WC_Countries ) {
    if ( '_country' === substr( $yaycheckout_key, -8 ) ) {
        $yaycheckout_names = WC()->countries->get_countries();
    } elseif ( '_state' === substr( $yaycheckout_key, -6 ) ) {
        $yaycheckout_country = $yaycheckout_read( $yaycheckout_prefix . '_country' );
        $yaycheckout_names   = '' !== $yaycheckout_country ? WC()->countries->get_states( $yaycheckout_country ) : [];

        // An empty list is a country without states, where WooCommerce hides the field: a leftover value means nothing.
        if ( [] === $yaycheckout_names ) {
            $yaycheckout_value = '';
        }
    }

    if ( is_array( $yaycheckout_names ) && isset( $yaycheckout_names[ $yaycheckout_value ] ) ) {
        $yaycheckout_value = $yaycheckout_names[ $yaycheckout_value ];
    }
}

$yaycheckout_placeholder = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'placeholder' );

if ( '' === $yaycheckout_placeholder ) {
    $yaycheckout_placeholder = \YayCheckout\Renderer\CheckoutFieldDefaults::label( $yaycheckout_key );
}

$yaycheckout_empty = '' === $yaycheckout_value;

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-field-value' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <div class="yaycheckout-field-value__value<?php echo $yaycheckout_empty ? ' is-empty' : ''; ?>" data-field-key="<?php echo esc_attr( $yaycheckout_key ); ?>" data-placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"><?php echo esc_html( $yaycheckout_empty ? $yaycheckout_placeholder : $yaycheckout_value ); ?></div>
</div>
