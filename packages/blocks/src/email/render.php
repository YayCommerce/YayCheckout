<?php
/**
 * Prints the billing_email control. It is always required; that rule lives in
 * RequiredFieldOverlay::is_required().
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; the block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label       = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label'] ? $attributes['label'] : __( 'Email address', 'yaycheckout' );
$yaycheckout_placeholder = isset( $attributes['placeholder'] ) && is_string( $attributes['placeholder'] ) ? $attributes['placeholder'] : '';
$yaycheckout_required    = true;
$yaycheckout_key         = 'billing_email';

$yaycheckout_wrapper = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-email form-row' ] );

if ( function_exists( 'woocommerce_form_field' ) ) {
    echo '<div ' . $yaycheckout_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.
    woocommerce_form_field(
        $yaycheckout_key,
        [
            'type'        => 'email',
            'label'       => $yaycheckout_label,
            'required'    => $yaycheckout_required,
            'placeholder' => $yaycheckout_placeholder,
            'validate'    => [ 'email' ],
            'return'      => false,
        ]
    );
    echo '</div>';
    return;
}

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <label for="<?php echo esc_attr( $yaycheckout_key ); ?>">
        <?php echo esc_html( $yaycheckout_label ); ?>
        <?php echo \YayCheckout\Services\RequiredIndicator::html( $yaycheckout_required, is_array( $attributes ) ? $attributes : [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes. ?>
    </label>
    <input
        type="email"
        class="input-text"
        name="<?php echo esc_attr( $yaycheckout_key ); ?>"
        id="<?php echo esc_attr( $yaycheckout_key ); ?>"
        placeholder="<?php echo esc_attr( $yaycheckout_placeholder ); ?>"
        autocomplete="email"
        <?php echo $yaycheckout_required ? 'required' : ''; ?>
    />
</div>
