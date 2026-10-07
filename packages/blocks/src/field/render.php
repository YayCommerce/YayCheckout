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

if ( '' === $yaycheckout_label ) {
    $yaycheckout_label = '' !== $yaycheckout_key ? $yaycheckout_key : __( 'Field', 'yaycheckout' );
}

if ( '' === $yaycheckout_key ) {
    static $yaycheckout_field_i = 0;
    ++$yaycheckout_field_i;
    $yaycheckout_key = 'yaycheckout_field_' . $yaycheckout_field_i;
}

$yaycheckout_wrapper = get_block_wrapper_attributes( array( 'class' => 'yaycheckout-field form-row' ) );

// The apartment line hides behind a link until the shopper asks for it. field/edit.tsx mirrors these keys.
// A required field is always shown, so checkout never fails on a field the shopper cannot see.
$yaycheckout_collapsed   = in_array( $yaycheckout_key, [ 'billing_address_2', 'shipping_address_2' ], true )
    && ! $yaycheckout_required
    && ( ! isset( $attributes['collapseBehindLink'] ) || ! empty( $attributes['collapseBehindLink'] ) );
$yaycheckout_reveal_text = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'revealText', __( '+ Add apartment, suite, etc.', 'yaycheckout' ) );
$yaycheckout_collapsible = $yaycheckout_key . '-collapsible';

if ( function_exists( 'woocommerce_form_field' ) ) {
    echo '<div ' . $yaycheckout_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.

    if ( $yaycheckout_collapsed ) {
        printf(
            '<button type="button" class="yaycheckout-field__reveal" aria-expanded="false" aria-controls="%1$s">%2$s</button><div class="yaycheckout-field__collapsible" id="%1$s" hidden>',
            esc_attr( $yaycheckout_collapsible ),
            esc_html( $yaycheckout_reveal_text )
        );
    }

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
    echo $yaycheckout_collapsed ? '</div></div>' : '</div>';
    return;
}

$yaycheckout_input_type = in_array( $yaycheckout_type, [ 'email', 'tel', 'password', 'hidden' ], true ) ? $yaycheckout_type : 'text';

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_collapsed ) : ?>
    <button type="button" class="yaycheckout-field__reveal" aria-expanded="false" aria-controls="<?php echo esc_attr( $yaycheckout_collapsible ); ?>"><?php echo esc_html( $yaycheckout_reveal_text ); ?></button>
    <div class="yaycheckout-field__collapsible" id="<?php echo esc_attr( $yaycheckout_collapsible ); ?>" hidden>
    <?php endif; ?>
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
    <?php if ( $yaycheckout_collapsed ) : ?>
    </div>
    <?php endif; ?>
</div>
