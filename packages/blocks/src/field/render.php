<?php
/**
 * Server render for yaycheckout/field.
 *
 * Outputs a real control so the storefront is typeable. Uses
 * woocommerce_form_field() when Woo is loaded so name/id/class match classic
 * checkout; otherwise a plain input. Attributes are read with defaults so
 * markup saved before an attribute existed still renders.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; field is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label        = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? $attributes['label'] : '';
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
    echo '<div ' . $yaycheckout_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.
    woocommerce_form_field(
        $yaycheckout_key,
        [
            'type'         => $yaycheckout_type,
            'label'        => $yaycheckout_label,
            'required'     => $yaycheckout_required,
            'placeholder'  => $yaycheckout_placeholder,
            'autocomplete' => $yaycheckout_autocomplete,
            'return'       => false,
        ]
    );
    echo '</div>';
    return;
}

$yaycheckout_input_type = in_array( $yaycheckout_type, [ 'email', 'tel', 'password', 'hidden' ], true ) ? $yaycheckout_type : 'text';

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <label for="<?php echo esc_attr( $yaycheckout_key ); ?>">
        <?php echo esc_html( $yaycheckout_label ); ?>
        <?php if ( $yaycheckout_required ) : ?>
            <abbr class="required" title="<?php echo esc_attr__( 'required', 'yaycheckout' ); ?>">*</abbr>
        <?php endif; ?>
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
