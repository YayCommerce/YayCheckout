<?php
/**
 * Server render for yaycheckout/step-button.
 *
 * The steps script validates the open step and opens the next one.
 * An empty label stays translatable; a saved label is the merchant's text.
 * The arrow is text after the label, hidden from screen readers.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Continue', 'yaycheckout' ) );
// A layout saved before the setting has no value: no arrow.
$yaycheckout_icon      = \YayCheckout\Renderer\ButtonArrow::glyph( isset( $attributes['arrow'] ) ? (string) $attributes['arrow'] : 'none', 'forward' );
$yaycheckout_icon_html = '' !== $yaycheckout_icon ? '<span class="yaycheckout-step-button__icon" aria-hidden="true">' . esc_html( $yaycheckout_icon ) . '</span>' : '';

$yaycheckout_wrapper = get_block_wrapper_attributes(
    [
        'class' => 'yaycheckout-step-button',
        'type'  => 'button',
    ]
);

?>
<button <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>><?php echo esc_html( $yaycheckout_label ); ?><?php echo $yaycheckout_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it is built above. ?></button>
