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
$yaycheckout_type        = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'type', 'text' );
$yaycheckout_placeholder = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'placeholder' );
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

// Remember value: the view script saves the entry on change, and CheckoutExtraFields answers get_value() with it.
$yaycheckout_value = $yaycheckout_remember ? \YayCheckout\Renderer\CheckoutFieldDefaults::value( $yaycheckout_key ) : null;

$yaycheckout_wrapper_attrs = [ 'class' => 'yaycheckout-extra-field form-row' ];

if ( $yaycheckout_remember ) {
    $yaycheckout_wrapper_attrs['data-yaycheckout-remember'] = '1';
}

$yaycheckout_woo_type         = 'datetime' === $yaycheckout_type ? 'datetime-local' : $yaycheckout_type;
$yaycheckout_checkbox_choices = 'checkbox' === $yaycheckout_type && \YayCheckout\Services\LayoutMarkupReader::checkbox_multiple( is_array( $attributes ) ? $attributes : [] );
// A checkbox label may hold a link ("I agree to the terms"); every other label, and the notices and order meta, stay plain.
$yaycheckout_label_html  = 'checkbox' === $yaycheckout_type
    ? \YayCheckout\Services\LayoutMarkupReader::rich_text( is_array( $attributes ) ? $attributes : [], 'label', $yaycheckout_label, \YayCheckout\Services\LayoutMarkupReader::LABEL_TAGS )
    : esc_html( $yaycheckout_label );
$yaycheckout_wrapper     = get_block_wrapper_attributes( $yaycheckout_wrapper_attrs );
$yaycheckout_describedby = '' !== $yaycheckout_description ? $yaycheckout_key . '-description' : '';

$yaycheckout_disclosure = \YayCheckout\Services\Disclosure::mode( $attributes, $yaycheckout_key, $yaycheckout_required, $yaycheckout_value );

if ( function_exists( 'woocommerce_form_field' ) && ! $yaycheckout_checkbox_choices ) {
    $yaycheckout_args = [
        'type'        => $yaycheckout_woo_type,
        // WooCommerce runs wp_kses_post() on it, which keeps LABEL_TAGS.
        'label'       => $yaycheckout_label_html,
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

    echo wp_kses( \YayCheckout\Services\Disclosure::open_if( $attributes, $yaycheckout_key, $yaycheckout_label, $yaycheckout_disclosure ), \YayCheckout\Services\Disclosure::ALLOWED_HTML );

    woocommerce_form_field( $yaycheckout_key, $yaycheckout_args, is_string( $yaycheckout_value ) ? $yaycheckout_value : null );
    echo wp_kses( \YayCheckout\Services\Disclosure::close_if( $yaycheckout_disclosure ), \YayCheckout\Services\Disclosure::ALLOWED_HTML ) . '</div>';
    return;
}

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    echo wp_kses( \YayCheckout\Services\Disclosure::open_if( $attributes, $yaycheckout_key, $yaycheckout_label, $yaycheckout_disclosure ), \YayCheckout\Services\Disclosure::ALLOWED_HTML );
    ?>
    <?php if ( $yaycheckout_checkbox_choices ) : ?>
        <span class="yaycheckout-extra-field__label" id="<?php echo esc_attr( $yaycheckout_key . '-label' ); ?>">
            <?php echo wp_kses( $yaycheckout_label_html, \YayCheckout\Services\LayoutMarkupReader::LABEL_TAGS ); ?>
            <?php echo wp_kses( \YayCheckout\Services\RequiredIndicator::html( $yaycheckout_required, is_array( $attributes ) ? $attributes : [] ), \YayCheckout\Services\RequiredIndicator::ALLOWED_HTML ); ?>
        </span>
    <?php elseif ( 'checkbox' !== $yaycheckout_type ) : ?>
        <label for="<?php echo esc_attr( $yaycheckout_key ); ?>">
            <?php echo esc_html( $yaycheckout_label ); ?>
            <?php echo wp_kses( \YayCheckout\Services\RequiredIndicator::html( $yaycheckout_required, is_array( $attributes ) ? $attributes : [] ), \YayCheckout\Services\RequiredIndicator::ALLOWED_HTML ); ?>
        </label>
    <?php endif; ?>
    <?php if ( 'select' === $yaycheckout_type ) : ?>
        <select class="input-text" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>"<?php echo '' !== $yaycheckout_describedby ? ' aria-describedby="' . esc_attr( $yaycheckout_describedby ) . '"' : ''; ?> <?php echo $yaycheckout_required ? 'required' : ''; ?>>
            <?php foreach ( $yaycheckout_options as $yaycheckout_option_value => $yaycheckout_option_label ) : ?>
                <option value="<?php echo esc_attr( (string) $yaycheckout_option_value ); ?>"><?php echo esc_html( $yaycheckout_option_label ); ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ( 'radio' === $yaycheckout_type ) : ?>
        <?php foreach ( $yaycheckout_options as $yaycheckout_option_value => $yaycheckout_option_label ) : ?>
            <label>
                <input type="radio" name="<?php echo esc_attr( $yaycheckout_key ); ?>" value="<?php echo esc_attr( (string) $yaycheckout_option_value ); ?>"<?php echo '' !== $yaycheckout_describedby ? ' aria-describedby="' . esc_attr( $yaycheckout_describedby ) . '"' : ''; ?> <?php echo $yaycheckout_required ? 'required' : ''; ?> />
                <?php echo esc_html( $yaycheckout_option_label ); ?>
            </label>
        <?php endforeach; ?>
    <?php elseif ( $yaycheckout_checkbox_choices ) : ?>
        <div class="yaycheckout-extra-field__choices" role="group" aria-labelledby="<?php echo esc_attr( $yaycheckout_key . '-label' ); ?>">
        <?php foreach ( $yaycheckout_options as $yaycheckout_option_value => $yaycheckout_option_label ) : ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( $yaycheckout_key ); ?>[]" value="<?php echo esc_attr( (string) $yaycheckout_option_value ); ?>"<?php echo '' !== $yaycheckout_describedby ? ' aria-describedby="' . esc_attr( $yaycheckout_describedby ) . '"' : ''; ?><?php checked( is_array( $yaycheckout_value ) && in_array( (string) $yaycheckout_option_value, $yaycheckout_value, true ) ); ?> />
                <?php echo esc_html( $yaycheckout_option_label ); ?>
            </label>
        <?php endforeach; ?>
        </div>
    <?php elseif ( 'checkbox' === $yaycheckout_type ) : ?>
        <label class="checkbox">
            <input type="checkbox" class="input-checkbox" name="<?php echo esc_attr( $yaycheckout_key ); ?>" id="<?php echo esc_attr( $yaycheckout_key ); ?>" value="1"<?php echo '' !== $yaycheckout_describedby ? ' aria-describedby="' . esc_attr( $yaycheckout_describedby ) . '"' : ''; ?> <?php echo $yaycheckout_required ? 'required' : ''; ?> />
            <?php echo wp_kses( $yaycheckout_label_html, \YayCheckout\Services\LayoutMarkupReader::LABEL_TAGS ); ?>
            <?php echo wp_kses( \YayCheckout\Services\RequiredIndicator::html( $yaycheckout_required, is_array( $attributes ) ? $attributes : [] ), \YayCheckout\Services\RequiredIndicator::ALLOWED_HTML ); ?>
        </label>
    <?php else : ?>
        <input
            type="<?php echo esc_attr( $yaycheckout_woo_type ); ?>"
            class="input-text"
            name="<?php echo esc_attr( $yaycheckout_key ); ?>"
            id="<?php echo esc_attr( $yaycheckout_key ); ?>"
            placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"
            <?php echo '' !== $yaycheckout_describedby ? ' aria-describedby="' . esc_attr( $yaycheckout_describedby ) . '"' : ''; ?>
            <?php echo $yaycheckout_required ? 'required' : ''; ?>
        />
    <?php endif; ?>
    <?php if ( '' !== $yaycheckout_description ) : ?>
        <span class="description" id="<?php echo esc_attr( $yaycheckout_key . '-description' ); ?>"><?php echo esc_html( $yaycheckout_description ); ?></span>
    <?php endif; ?>
    <?php echo wp_kses( \YayCheckout\Services\Disclosure::close_if( $yaycheckout_disclosure ), \YayCheckout\Services\Disclosure::ALLOWED_HTML ); ?>
</div>
