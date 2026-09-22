<?php
/**
 * Server render for yaycheckout/contact.
 *
 * Contact is a leaf billing_email control, not a section with inner Fields.
 * Attributes are read with defaults so markup saved before an attribute
 * existed still renders.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; contact is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label       = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label'] ? $attributes['label'] : __( 'Email address', 'yaycheckout' );
$yaycheckout_placeholder = isset( $attributes['placeholder'] ) && is_string( $attributes['placeholder'] ) ? $attributes['placeholder'] : '';
$yaycheckout_required    = true;
$yaycheckout_key         = 'billing_email';

$yaycheckout_wrapper = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-contact form-row' ] );

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
        <?php if ( $yaycheckout_required ) : ?>
            <abbr class="required" title="<?php echo esc_attr__( 'required', 'yaycheckout' ); ?>">*</abbr>
        <?php endif; ?>
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
