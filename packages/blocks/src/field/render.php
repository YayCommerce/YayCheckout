<?php
/**
 * Prints one address field, through woocommerce_form_field() when WooCommerce
 * is loaded so name, id and class match classic checkout.
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
$yaycheckout_description  = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'description' );

if ( '' === $yaycheckout_label ) {
    $yaycheckout_label = '' !== $yaycheckout_key ? $yaycheckout_key : __( 'Field', 'yaycheckout' );
}

if ( '' === $yaycheckout_key ) {
    static $yaycheckout_field_i = 0;
    ++$yaycheckout_field_i;
    $yaycheckout_key = 'yaycheckout_field_' . $yaycheckout_field_i;
}

$yaycheckout_wrapper = get_block_wrapper_attributes( array( 'class' => 'yaycheckout-field form-row' ) );

$yaycheckout_collapsed = \YayCheckout\Services\RevealLink::collapsed( $attributes, $yaycheckout_key, $yaycheckout_required );

if ( function_exists( 'woocommerce_form_field' ) ) {
    echo '<div ' . $yaycheckout_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.

    if ( $yaycheckout_collapsed ) {
        echo \YayCheckout\Services\RevealLink::open( $yaycheckout_key, \YayCheckout\Services\RevealLink::text( $attributes, $yaycheckout_key, $yaycheckout_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- open() escapes.
    }

    woocommerce_form_field(
        $yaycheckout_key,
        [
            'type'         => $yaycheckout_type,
            'label'        => $yaycheckout_label,
            'required'     => $yaycheckout_required,
            'placeholder'  => $yaycheckout_placeholder,
            'autocomplete' => $yaycheckout_autocomplete,
            // WooCommerce runs only wp_kses_post() on it, so a typed tag would render.
            'description'  => esc_html( $yaycheckout_description ),
            'return'       => false,
        ]
    );
    echo $yaycheckout_collapsed ? \YayCheckout\Services\RevealLink::close() . '</div>' : '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup.
    return;
}

$yaycheckout_input_type  = in_array( $yaycheckout_type, [ 'email', 'tel', 'password', 'hidden' ], true ) ? $yaycheckout_type : 'text';
$yaycheckout_describedby = '' !== $yaycheckout_description ? ' aria-describedby="' . esc_attr( $yaycheckout_key . '-description' ) . '"' : '';

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    if ( $yaycheckout_collapsed ) {
        echo \YayCheckout\Services\RevealLink::open( $yaycheckout_key, \YayCheckout\Services\RevealLink::text( $attributes, $yaycheckout_key, $yaycheckout_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- open() escapes.
    }
    ?>
    <label for="<?php echo esc_attr( $yaycheckout_key ); ?>">
        <?php echo esc_html( $yaycheckout_label ); ?>
        <?php echo \YayCheckout\Services\RequiredIndicator::html( $yaycheckout_required, is_array( $attributes ) ? $attributes : [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes. ?>
    </label>
    <?php if ( 'textarea' === $yaycheckout_type ) : ?>
        <textarea class="input-text" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>" placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"<?php echo $yaycheckout_describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built. ?> <?php echo $yaycheckout_required ? 'required' : ''; ?>></textarea>
    <?php elseif ( 'checkbox' === $yaycheckout_type ) : ?>
        <input type="checkbox" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>" value="1"<?php echo $yaycheckout_describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built. ?> <?php echo $yaycheckout_required ? 'required' : ''; ?> />
    <?php else : ?>
        <input
            type="<?php echo esc_attr( $yaycheckout_input_type ); ?>"
            class="input-text"
            name="<?php echo esc_attr( $yaycheckout_key ); ?>"
            id="<?php echo esc_attr( $yaycheckout_key ); ?>"
            placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"
            autocomplete="<?php echo esc_attr( $yaycheckout_autocomplete ); ?>"
            <?php echo $yaycheckout_describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built. ?>
            <?php echo $yaycheckout_required ? 'required' : ''; ?>
        />
    <?php endif; ?>
    <?php if ( '' !== $yaycheckout_description ) : ?>
        <span class="description" id="<?php echo esc_attr( $yaycheckout_key . '-description' ); ?>"><?php echo esc_html( $yaycheckout_description ); ?></span>
    <?php endif; ?>
    <?php echo $yaycheckout_collapsed ? \YayCheckout\Services\RevealLink::close() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?>
</div>
