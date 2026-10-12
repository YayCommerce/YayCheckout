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
$yaycheckout_markup = null !== $yaycheckout_layout ? $yaycheckout_layout->content : '';
// Key => label of each field the layout prints that this block can show; any other key is dropped.
$yaycheckout_showable = '' !== $yaycheckout_markup ? \YayCheckout\Renderer\ShowableFields::labels( $yaycheckout_markup ) : [];

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

$yaycheckout_values = [];
$yaycheckout_labels = [];

foreach ( $yaycheckout_keys as $yaycheckout_key ) {
    $yaycheckout_values[] = \YayCheckout\Renderer\ShowableFields::value( $yaycheckout_key, $yaycheckout_markup );
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
