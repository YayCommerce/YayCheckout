<?php
/**
 * Prints one address field, through woocommerce_form_field() when WooCommerce
 * is loaded so name, id and class match classic checkout. On the storefront a
 * billing_ or shipping_ Key renders WooCommerce's own definition, with the
 * layout's label, placeholder and required flag laid over it, and the checkout
 * value, so checkout.js refreshes totals and saved addresses prefill.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; field is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label        = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'label' );
$yaycheckout_key          = isset( $attributes['fieldKey'] ) && is_string( $attributes['fieldKey'] ) ? $attributes['fieldKey'] : '';
$yaycheckout_type         = isset( $attributes['type'] ) && is_string( $attributes['type'] ) && '' !== $attributes['type'] ? $attributes['type'] : 'text';
$yaycheckout_placeholder  = isset( $attributes['placeholder'] ) && is_string( $attributes['placeholder'] ) ? $attributes['placeholder'] : '';
$yaycheckout_required     = ! empty( $attributes['required'] );
$yaycheckout_autocomplete = isset( $attributes['autocomplete'] ) && is_string( $attributes['autocomplete'] ) ? $attributes['autocomplete'] : '';

if ( '' === $yaycheckout_label ) {
    $yaycheckout_label = '' !== $yaycheckout_key ? $yaycheckout_key : __( 'Field', 'yaycheckout' );
}

if ( '' === $yaycheckout_key ) {
    static $yaycheckout_field_i = 0;
    ++$yaycheckout_field_i;
    $yaycheckout_key = 'yaycheckout_field_' . $yaycheckout_field_i;
}

$yaycheckout_wrapper = get_block_wrapper_attributes( array( 'class' => 'yaycheckout-field form-row' ) );

if ( function_exists( 'woocommerce_form_field' ) ) {
    $yaycheckout_args  = [
        'type'         => $yaycheckout_type,
        'label'        => $yaycheckout_label,
        'required'     => $yaycheckout_required,
        'placeholder'  => $yaycheckout_placeholder,
        'autocomplete' => $yaycheckout_autocomplete,
        'return'       => false,
    ];
    $yaycheckout_value = null;

    // No cart outside the storefront (admin, REST, CLI): the editor preview keeps the layout's own args.
    // The prefixes are RequiredFieldOverlay::ADDRESS_FIELDSETS (owner).
    if ( function_exists( 'WC' ) && WC()->cart instanceof WC_Cart && preg_match( '/^(billing|shipping)_/', $yaycheckout_key, $yaycheckout_match ) ) {
        $yaycheckout_definition = WC()->checkout()->get_checkout_fields( $yaycheckout_match[1] )[ $yaycheckout_key ] ?? null;

        if ( is_array( $yaycheckout_definition ) ) {
            // WooCommerce owns classes, type and locale; the layout owns label, placeholder and required.
            $yaycheckout_args = array_merge(
                $yaycheckout_definition,
                [
                    'label'    => $yaycheckout_label,
                    'required' => $yaycheckout_required,
                    'return'   => false,
                ],
                '' !== $yaycheckout_placeholder ? [ 'placeholder' => $yaycheckout_placeholder ] : []
            );

            $yaycheckout_value = WC()->checkout()->get_value( $yaycheckout_key );
        }
    }

    echo '<div ' . $yaycheckout_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.
    woocommerce_form_field( $yaycheckout_key, $yaycheckout_args, $yaycheckout_value );
    echo '</div>';
    return;
}

$yaycheckout_input_type = in_array( $yaycheckout_type, [ 'email', 'tel', 'password', 'hidden' ], true ) ? $yaycheckout_type : 'text';

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <label for="<?php echo esc_attr( $yaycheckout_key ); ?>">
        <?php echo esc_html( $yaycheckout_label ); ?>
        <?php echo \YayCheckout\Services\RequiredIndicator::html( $yaycheckout_required, is_array( $attributes ) ? $attributes : [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes. ?>
    </label>
    <?php if ( 'textarea' === $yaycheckout_type ) : ?>
        <textarea class="input-text" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>" placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>" <?php echo $yaycheckout_required ? 'required' : ''; ?>></textarea>
    <?php elseif ( 'checkbox' === $yaycheckout_type ) : ?>
        <input type="checkbox" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>" value="1" <?php echo $yaycheckout_required ? 'required' : ''; ?> />
    <?php else : ?>
        <input
            type="<?php echo esc_attr( $yaycheckout_input_type ); ?>"
            class="input-text"
            name="<?php echo esc_attr( $yaycheckout_key ); ?>"
            id="<?php echo esc_attr( $yaycheckout_key ); ?>"
            placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"
            autocomplete="<?php echo esc_attr( $yaycheckout_autocomplete ); ?>"
            <?php echo $yaycheckout_required ? 'required' : ''; ?>
        />
    <?php endif; ?>
</div>
