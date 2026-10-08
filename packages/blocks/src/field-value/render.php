<?php
/**
 * Prints one layout field's value as the shopper last saved it, or the placeholder, muted, while
 * it is empty. view.js keeps it in step with the form as they type. Prints nothing until the block
 * points at a field the served layout holds.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; the block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_key    = isset( $attributes['fieldKey'] ) && is_string( $attributes['fieldKey'] ) ? trim( $attributes['fieldKey'] ) : '';
$yaycheckout_layout = '' !== $yaycheckout_key ? \YayCheckout\Renderer\CheckoutPage::get_instance()->layout() : null;
// ponytail: parses the layout once per Field Value block; memoise if a layout ever holds dozens of them.
$yaycheckout_fields = null !== $yaycheckout_layout ? \YayCheckout\Services\LayoutMarkupReader::fields( $yaycheckout_layout->content ) : [];

// Only a field the layout prints; any other key could read a value this block was never meant to show.
// A checkbox's value is always 1, a password must never be echoed, and a hidden field holds nothing typed.
if (
    ! isset( $yaycheckout_fields[ $yaycheckout_key ] )
    || in_array( $yaycheckout_fields[ $yaycheckout_key ]['type'], [ 'checkbox', 'password', 'hidden' ], true )
    || ! preg_match( '/^(billing|shipping)_/', $yaycheckout_key, $yaycheckout_match )
) {
    return;
}

$yaycheckout_read = static function ( string $key ): string {
    $value = \YayCheckout\Renderer\CheckoutFieldDefaults::value( $key );

    return is_scalar( $value ) ? trim( (string) $value ) : '';
};

// A field the country's locale hides (State for VN) means nothing, even with a value left over.
$yaycheckout_value = \YayCheckout\Renderer\CheckoutFieldDefaults::hidden_for_country( $yaycheckout_key ) ? '' : $yaycheckout_read( $yaycheckout_key );

// Country and state show by name, as the selects do. The names carry entities, which esc_html() keeps.
if ( '' !== $yaycheckout_value && function_exists( 'WC' ) && WC()->countries instanceof WC_Countries ) {
    $yaycheckout_names = [];

    if ( '_country' === substr( $yaycheckout_key, -8 ) ) {
        $yaycheckout_names = WC()->countries->get_countries();
    } elseif ( '_state' === substr( $yaycheckout_key, -6 ) ) {
        $yaycheckout_country = $yaycheckout_read( $yaycheckout_match[1] . '_country' );
        $yaycheckout_names   = '' !== $yaycheckout_country ? WC()->countries->get_states( $yaycheckout_country ) : [];
    }

    if ( is_array( $yaycheckout_names ) && isset( $yaycheckout_names[ $yaycheckout_value ] ) ) {
        $yaycheckout_value = $yaycheckout_names[ $yaycheckout_value ];
    }
}

// The label the layout's field prints; an empty one falls back as email/render.php and field/render.php do.
$yaycheckout_field_label = $yaycheckout_fields[ $yaycheckout_key ]['label'];

if ( '' === $yaycheckout_field_label ) {
    $yaycheckout_field_label = 'billing_email' === $yaycheckout_key ? __( 'Email address', 'yaycheckout' ) : $yaycheckout_key;
}

// view.js swaps in the form's live label where no placeholder was typed, so only a typed one goes in data-placeholder.
$yaycheckout_placeholder = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'placeholder' );
$yaycheckout_empty       = '' === $yaycheckout_value;

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-field-value' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( ! empty( $attributes['showLabel'] ) ) : ?>
        <div class="yaycheckout-field-value__label"><?php echo esc_html( $yaycheckout_field_label ); ?></div>
    <?php endif; ?>
    <div class="yaycheckout-field-value__value<?php echo $yaycheckout_empty ? ' is-empty' : ''; ?>" data-field-key="<?php echo esc_attr( $yaycheckout_key ); ?>" data-placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"><?php echo esc_html( $yaycheckout_empty ? ( '' !== $yaycheckout_placeholder ? $yaycheckout_placeholder : $yaycheckout_field_label ) : $yaycheckout_value ); ?></div>
</div>
