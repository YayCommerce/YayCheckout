<?php
/**
 * Prints one address field, through woocommerce_form_field() when WooCommerce
 * is loaded so name, id, class and the shopper's value match classic checkout.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; field is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label        = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'label' );
$yaycheckout_key          = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'fieldKey' );
$yaycheckout_type         = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'type', 'text' );
$yaycheckout_placeholder  = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'placeholder' );
$yaycheckout_required     = ! empty( $attributes['required'] );
$yaycheckout_autocomplete = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'autocomplete' );
$yaycheckout_description  = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'description' );

if ( '' === $yaycheckout_label ) {
    $yaycheckout_label = '' !== $yaycheckout_key ? $yaycheckout_key : __( 'Field', 'yaycheckout' );
}

if ( '' === $yaycheckout_key ) {
    static $yaycheckout_field_i = 0;
    ++$yaycheckout_field_i;
    $yaycheckout_key = 'yaycheckout_field_' . $yaycheckout_field_i;
}

$yaycheckout_wrapper = \YayCheckout\Renderer\HideWhenEmpty::wrapper(
    array(
        'class'                                          => 'yaycheckout-field form-row',
        \YayCheckout\Renderer\HideWhenEmpty::LOCALE_ATTR => '1',
    ),
    $attributes,
    \YayCheckout\Renderer\CheckoutFieldDefaults::hidden_for_country( $yaycheckout_key )
);

$yaycheckout_value = \YayCheckout\Renderer\CheckoutFieldDefaults::value( $yaycheckout_key );

$yaycheckout_disclosure = \YayCheckout\Services\Disclosure::mode( $attributes, $yaycheckout_key, $yaycheckout_required, $yaycheckout_value );

if ( function_exists( 'woocommerce_form_field' ) ) {
    echo '<div ' . $yaycheckout_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.

    echo \YayCheckout\Services\Disclosure::open_if( $attributes, $yaycheckout_key, $yaycheckout_label, $yaycheckout_disclosure ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- open() escapes.

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
            'class'        => \YayCheckout\Renderer\CheckoutFieldDefaults::classes( $yaycheckout_key ),
            'return'       => false,
        ],
        $yaycheckout_value
    );
    echo \YayCheckout\Services\Disclosure::close_if( $yaycheckout_disclosure ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup.
    return;
}

$yaycheckout_input_type  = in_array( $yaycheckout_type, [ 'email', 'tel', 'password', 'hidden' ], true ) ? $yaycheckout_type : 'text';
$yaycheckout_describedby = '' !== $yaycheckout_description ? ' aria-describedby="' . esc_attr( $yaycheckout_key . '-description' ) . '"' : '';

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    echo \YayCheckout\Services\Disclosure::open_if( $attributes, $yaycheckout_key, $yaycheckout_label, $yaycheckout_disclosure ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- open() escapes.
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
    <?php echo \YayCheckout\Services\Disclosure::close_if( $yaycheckout_disclosure ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?>
</div>
