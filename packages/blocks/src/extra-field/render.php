<?php
/**
 * Server render for yaycheckout/extra-field.
 *
 * Custom merchant field, not a Woo address catalog key. Attributes are read
 * with defaults so markup saved before an attribute existed still renders.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; extra-field is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label       = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? $attributes['label'] : '';
$yaycheckout_key         = \YayCheckout\Services\LayoutMarkupReader::extra_field_key( $attributes );
$yaycheckout_type        = isset( $attributes['type'] ) && is_string( $attributes['type'] ) && '' !== $attributes['type'] ? $attributes['type'] : 'text';
$yaycheckout_placeholder = isset( $attributes['placeholder'] ) && is_string( $attributes['placeholder'] ) ? $attributes['placeholder'] : '';
$yaycheckout_required    = ! empty( $attributes['required'] );
$yaycheckout_options     = \YayCheckout\Services\LayoutMarkupReader::options( $attributes['options'] ?? [] );

// Without a Key there is no posted name to persist; the editor blocks saving until one is set.
if ( '' === $yaycheckout_key ) {
    return;
}

if ( '' === $yaycheckout_label ) {
    $yaycheckout_label = __( 'Extra field', 'yaycheckout' );
}

$yaycheckout_woo_type         = 'datetime' === $yaycheckout_type ? 'text' : $yaycheckout_type;
$yaycheckout_checkbox_choices = 'checkbox' === $yaycheckout_type && ! empty( $yaycheckout_options );
$yaycheckout_wrapper          = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-extra-field form-row' ] );

if ( function_exists( 'woocommerce_form_field' ) && ! $yaycheckout_checkbox_choices ) {
    $yaycheckout_args = [
        'type'        => $yaycheckout_woo_type,
        'label'       => $yaycheckout_label,
        'required'    => $yaycheckout_required,
        'placeholder' => $yaycheckout_placeholder,
        'return'      => false,
    ];

    if ( in_array( $yaycheckout_type, [ 'select', 'radio' ], true ) ) {
        $yaycheckout_args['options'] = $yaycheckout_options;
    }

    echo '<div ' . $yaycheckout_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.
    woocommerce_form_field( $yaycheckout_key, $yaycheckout_args );
    echo '</div>';
    return;
}

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <label for="<?php echo esc_attr( $yaycheckout_key ); ?>">
        <?php echo esc_html( $yaycheckout_label ); ?>
        <?php echo \YayCheckout\Services\CheckoutFieldRequirements::indicator_html( $yaycheckout_required, is_array( $attributes ) ? $attributes : [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- indicator_html escapes. ?>
    </label>
    <?php if ( 'select' === $yaycheckout_type ) : ?>
        <select class="input-text" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>" <?php echo $yaycheckout_required ? 'required' : ''; ?>>
            <?php foreach ( $yaycheckout_options as $yaycheckout_option_value => $yaycheckout_option_label ) : ?>
                <option value="<?php echo esc_attr( (string) $yaycheckout_option_value ); ?>"><?php echo esc_html( $yaycheckout_option_label ); ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ( 'radio' === $yaycheckout_type ) : ?>
        <?php foreach ( $yaycheckout_options as $yaycheckout_option_value => $yaycheckout_option_label ) : ?>
            <label>
                <input type="radio" name="<?php echo esc_attr( $yaycheckout_key ); ?>" value="<?php echo esc_attr( (string) $yaycheckout_option_value ); ?>" <?php echo $yaycheckout_required ? 'required' : ''; ?> />
                <?php echo esc_html( $yaycheckout_option_label ); ?>
            </label>
        <?php endforeach; ?>
    <?php elseif ( 'checkbox' === $yaycheckout_type && ! empty( $yaycheckout_options ) ) : ?>
        <?php foreach ( $yaycheckout_options as $yaycheckout_option_value => $yaycheckout_option_label ) : ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( $yaycheckout_key ); ?>[]" value="<?php echo esc_attr( (string) $yaycheckout_option_value ); ?>" />
                <?php echo esc_html( $yaycheckout_option_label ); ?>
            </label>
        <?php endforeach; ?>
    <?php elseif ( 'checkbox' === $yaycheckout_type ) : ?>
        <input type="checkbox" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>" value="1" <?php echo $yaycheckout_required ? 'required' : ''; ?> />
    <?php else : ?>
        <input
            type="<?php echo 'datetime' === $yaycheckout_type ? 'datetime-local' : 'text'; ?>"
            class="input-text"
            name="<?php echo esc_attr( $yaycheckout_key ); ?>"
            id="<?php echo esc_attr( $yaycheckout_key ); ?>"
            placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"
            <?php echo $yaycheckout_required ? 'required' : ''; ?>
        />
    <?php endif; ?>
</div>
