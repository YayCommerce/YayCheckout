<?php
/**
 * Prints a merchant-defined field, posted and stored on the order under its Key.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; extra-field is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label       = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'label' );
$yaycheckout_key         = \YayCheckout\Services\LayoutMarkupReader::extra_field_key( $attributes );
$yaycheckout_type        = isset( $attributes['type'] ) && is_string( $attributes['type'] ) && '' !== $attributes['type'] ? $attributes['type'] : 'text';
$yaycheckout_placeholder = isset( $attributes['placeholder'] ) && is_string( $attributes['placeholder'] ) ? $attributes['placeholder'] : '';
$yaycheckout_required    = ! empty( $attributes['required'] );
$yaycheckout_options     = \YayCheckout\Services\LayoutMarkupReader::options( $attributes['options'] ?? [] );
$yaycheckout_description = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'description' );
$yaycheckout_remember    = ! empty( $attributes['rememberValue'] );

// Without a valid Key there is no posted name to persist; the editor blocks saving until one is set.
if ( '' === $yaycheckout_key ) {
    return;
}

if ( '' === $yaycheckout_label ) {
    $yaycheckout_label = __( 'Extra field', 'yaycheckout' );
}

// Remember value: the view script saves the entry on change; CheckoutExtraFields hands it back here after a reload.
$yaycheckout_value = $yaycheckout_remember ? \YayCheckout\Renderer\CheckoutExtraFields::remembered_value( $yaycheckout_key ) : null;

$yaycheckout_wrapper_attrs = [ 'class' => 'yaycheckout-extra-field form-row' ];

if ( $yaycheckout_remember ) {
    $yaycheckout_wrapper_attrs['data-yaycheckout-remember'] = '1';
}

$yaycheckout_woo_type         = 'datetime' === $yaycheckout_type ? 'datetime-local' : $yaycheckout_type;
$yaycheckout_checkbox_choices = 'checkbox' === $yaycheckout_type && ! empty( $yaycheckout_options );
$yaycheckout_wrapper          = get_block_wrapper_attributes( $yaycheckout_wrapper_attrs );
$yaycheckout_describedby      = '' !== $yaycheckout_description ? ' aria-describedby="' . esc_attr( $yaycheckout_key . '-description' ) . '"' : '';

// A field holding a remembered value stays open, so a reload doesn't hide it.
$yaycheckout_collapsed = \YayCheckout\Services\RevealLink::collapsed( $attributes, $yaycheckout_key, $yaycheckout_required ) && null === $yaycheckout_value;

if ( function_exists( 'woocommerce_form_field' ) && ! $yaycheckout_checkbox_choices ) {
    $yaycheckout_args = [
        'type'        => $yaycheckout_woo_type,
        'label'       => $yaycheckout_label,
        'required'    => $yaycheckout_required,
        'placeholder' => $yaycheckout_placeholder,
        // WooCommerce runs only wp_kses_post() on it, so a typed tag would render.
        'description' => esc_html( $yaycheckout_description ),
        'return'      => false,
    ];

    if ( in_array( $yaycheckout_type, [ 'select', 'radio' ], true ) ) {
        $yaycheckout_args['options'] = $yaycheckout_options;
    }

    echo '<div ' . $yaycheckout_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.

    if ( $yaycheckout_collapsed ) {
        echo \YayCheckout\Services\RevealLink::open( $yaycheckout_key, \YayCheckout\Services\RevealLink::text( $attributes, $yaycheckout_key, $yaycheckout_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- open() escapes.
    }

    woocommerce_form_field( $yaycheckout_key, $yaycheckout_args, is_string( $yaycheckout_value ) ? $yaycheckout_value : null );
    echo $yaycheckout_collapsed ? \YayCheckout\Services\RevealLink::close() . '</div>' : '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup.
    return;
}

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
    <?php if ( 'select' === $yaycheckout_type ) : ?>
        <select class="input-text" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>"<?php echo $yaycheckout_describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built. ?> <?php echo $yaycheckout_required ? 'required' : ''; ?>>
            <?php foreach ( $yaycheckout_options as $yaycheckout_option_value => $yaycheckout_option_label ) : ?>
                <option value="<?php echo esc_attr( (string) $yaycheckout_option_value ); ?>"><?php echo esc_html( $yaycheckout_option_label ); ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ( 'radio' === $yaycheckout_type ) : ?>
        <?php foreach ( $yaycheckout_options as $yaycheckout_option_value => $yaycheckout_option_label ) : ?>
            <label>
                <input type="radio" name="<?php echo esc_attr( $yaycheckout_key ); ?>" value="<?php echo esc_attr( (string) $yaycheckout_option_value ); ?>"<?php echo $yaycheckout_describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built. ?> <?php echo $yaycheckout_required ? 'required' : ''; ?> />
                <?php echo esc_html( $yaycheckout_option_label ); ?>
            </label>
        <?php endforeach; ?>
    <?php elseif ( 'checkbox' === $yaycheckout_type && ! empty( $yaycheckout_options ) ) : ?>
        <?php foreach ( $yaycheckout_options as $yaycheckout_option_value => $yaycheckout_option_label ) : ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( $yaycheckout_key ); ?>[]" value="<?php echo esc_attr( (string) $yaycheckout_option_value ); ?>"<?php echo $yaycheckout_describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built. ?><?php checked( is_array( $yaycheckout_value ) && in_array( (string) $yaycheckout_option_value, $yaycheckout_value, true ) ); ?> />
                <?php echo esc_html( $yaycheckout_option_label ); ?>
            </label>
        <?php endforeach; ?>
    <?php elseif ( 'checkbox' === $yaycheckout_type ) : ?>
        <input type="checkbox" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>" value="1"<?php echo $yaycheckout_describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built. ?> <?php echo $yaycheckout_required ? 'required' : ''; ?> />
    <?php else : ?>
        <input
            type="<?php echo esc_attr( $yaycheckout_woo_type ); ?>"
            class="input-text"
            name="<?php echo esc_attr( $yaycheckout_key ); ?>"
            id="<?php echo esc_attr( $yaycheckout_key ); ?>"
            placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"
            <?php echo $yaycheckout_describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built. ?>
            <?php echo $yaycheckout_required ? 'required' : ''; ?>
        />
    <?php endif; ?>
    <?php if ( '' !== $yaycheckout_description ) : ?>
        <span class="description" id="<?php echo esc_attr( $yaycheckout_key . '-description' ); ?>"><?php echo esc_html( $yaycheckout_description ); ?></span>
    <?php endif; ?>
    <?php echo $yaycheckout_collapsed ? \YayCheckout\Services\RevealLink::close() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?>
</div>
