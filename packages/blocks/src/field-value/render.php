<?php
/**
 * Prints the values of one or more layout fields as the shopper last saved them, joined by the
 * separator, or the placeholder, muted, while all are empty. view.js keeps it in step with the form
 * as they type. Prints nothing until the block points at a field the served layout holds. Counts as
 * empty for Hide when empty while every field is.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; the block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_keys      = isset( $attributes['fieldKeys'] ) && is_array( $attributes['fieldKeys'] ) ? $attributes['fieldKeys'] : [];
$yaycheckout_keys      = array_values( array_unique( array_map( 'trim', array_filter( $yaycheckout_keys, 'is_string' ) ) ) );
$yaycheckout_separator = isset( $attributes['separator'] ) && is_string( $attributes['separator'] ) ? $attributes['separator'] : ', ';

if ( [] === $yaycheckout_keys ) {
    return;
}

$yaycheckout_layout = \YayCheckout\Renderer\CheckoutPage::get_instance()->layout();
// Parses the layout once per Field Value block; memoise if a layout ever holds dozens of them.
$yaycheckout_fields = null !== $yaycheckout_layout ? \YayCheckout\Services\LayoutMarkupReader::fields( $yaycheckout_layout->content ) : [];
$yaycheckout_extra  = null !== $yaycheckout_layout ? \YayCheckout\Services\LayoutMarkupReader::extra_fields( $yaycheckout_layout->content ) : [];

// Mirrored by UNSHOWABLE_TYPES in edit.tsx: a checkbox's value is always 1, a password must never be
// echoed, and a hidden field holds nothing typed.
$yaycheckout_unshowable = [ 'checkbox', 'password', 'hidden' ];

// Key => label of each field the layout prints that this block can show; any other key could read a value
// it was never meant to show. Empty labels fall back as email/render.php, field/render.php and
// extra-field/render.php do. An Extra field's single checkbox is left out: its value is always 1.
$yaycheckout_showable = [];

foreach ( $yaycheckout_fields as $yaycheckout_key => $yaycheckout_field ) {
    if ( 1 === preg_match( '/^(billing|shipping)_/', $yaycheckout_key ) && ! in_array( $yaycheckout_field['type'], $yaycheckout_unshowable, true ) ) {
        $yaycheckout_showable[ $yaycheckout_key ] = '' !== $yaycheckout_field['label']
            ? $yaycheckout_field['label']
            : ( 'billing_email' === $yaycheckout_key ? __( 'Email address', 'yaycheckout' ) : $yaycheckout_key );
    }
}

foreach ( $yaycheckout_extra as $yaycheckout_key => $yaycheckout_field ) {
    if ( ! isset( $yaycheckout_showable[ $yaycheckout_key ] ) && ! ( 'checkbox' === $yaycheckout_field['type'] && empty( $yaycheckout_field['options'] ) ) ) {
        $yaycheckout_showable[ $yaycheckout_key ] = '' !== $yaycheckout_field['label'] ? $yaycheckout_field['label'] : __( 'Extra field', 'yaycheckout' );
    }
}

$yaycheckout_keys = array_values(
    array_filter(
        $yaycheckout_keys,
        static function ( string $key ) use ( $yaycheckout_showable ): bool {
            return isset( $yaycheckout_showable[ $key ] );
        }
    )
);

if ( [] === $yaycheckout_keys ) {
    return;
}

$yaycheckout_read = static function ( string $key ): string {
    $value = \YayCheckout\Renderer\CheckoutFieldDefaults::value( $key );

    return is_scalar( $value ) ? trim( (string) $value ) : '';
};

// One field's value as its form control shows it.
$yaycheckout_value_of = static function ( string $key ) use ( $yaycheckout_read, $yaycheckout_fields, $yaycheckout_extra ): string {
    if ( ! isset( $yaycheckout_fields[ $key ] ) ) {
        $field = $yaycheckout_extra[ $key ];

        // As extra-field/render.php: only a remembered field prints a value on load; view.js fills the rest.
        if ( empty( $field['remember'] ) ) {
            return '';
        }

        $value = \YayCheckout\Renderer\CheckoutFieldDefaults::value( $key );
        $names = [];

        // A choice shows its option's label, as the form does; ticked checkboxes are listed with commas.
        foreach ( is_array( $value ) ? $value : [ $value ] as $choice ) {
            $choice = is_scalar( $choice ) ? trim( (string) $choice ) : '';

            if ( '' !== $choice ) {
                $names[] = isset( $field['options'][ $choice ] ) ? $field['options'][ $choice ] : $choice;
            }
        }

        return implode( ', ', $names );
    }

    // A field the country's locale hides (State for VN) means nothing, even with a value left over.
    $value = \YayCheckout\Renderer\CheckoutFieldDefaults::hidden_for_country( $key ) ? '' : $yaycheckout_read( $key );

    // Country and state show by name, as the selects do. The names carry entities, which esc_html() keeps.
    if ( '' === $value || ! function_exists( 'WC' ) || ! WC()->countries instanceof WC_Countries ) {
        return $value;
    }

    $names = [];

    if ( '_country' === substr( $key, -8 ) ) {
        $names = WC()->countries->get_countries();
    } elseif ( '_state' === substr( $key, -6 ) ) {
        $country = $yaycheckout_read( substr( $key, 0, -6 ) . '_country' );
        $names   = '' !== $country ? WC()->countries->get_states( $country ) : [];
    }

    return is_array( $names ) && isset( $names[ $value ] ) ? $names[ $value ] : $value;
};

$yaycheckout_values = [];
$yaycheckout_labels = [];

foreach ( $yaycheckout_keys as $yaycheckout_key ) {
    $yaycheckout_values[] = $yaycheckout_value_of( $yaycheckout_key );
    $yaycheckout_labels[] = $yaycheckout_showable[ $yaycheckout_key ];
}

// Empty values are skipped, so a half-filled block shows no dangling separator.
$yaycheckout_value      = implode( $yaycheckout_separator, array_filter( $yaycheckout_values, 'strlen' ) );
$yaycheckout_label_text = implode( $yaycheckout_separator, $yaycheckout_labels );

// view.js swaps in the form's live labels where no placeholder was typed, so only a typed one goes in data-placeholder.
$yaycheckout_placeholder = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'placeholder' );
$yaycheckout_empty       = '' === $yaycheckout_value;

?>
<div <?php echo \YayCheckout\Renderer\HideWhenEmpty::wrapper( [ 'class' => 'yaycheckout-field-value' ], $attributes, $yaycheckout_empty ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <span class="yaycheckout-field-value__value<?php echo $yaycheckout_empty ? ' is-empty' : ''; ?>" data-field-keys="<?php echo esc_attr( (string) wp_json_encode( $yaycheckout_keys ) ); ?>" data-separator="<?php echo esc_attr( $yaycheckout_separator ); ?>" data-labels="<?php echo esc_attr( (string) wp_json_encode( $yaycheckout_labels ) ); ?>" data-placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"><?php echo esc_html( $yaycheckout_empty ? ( '' !== $yaycheckout_placeholder ? $yaycheckout_placeholder : $yaycheckout_label_text ) : $yaycheckout_value ); ?></span>
</div>
