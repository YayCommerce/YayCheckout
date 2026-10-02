<?php
/**
 * Server render for yaycheckout/step-button.
 *
 * The steps script validates the open step and opens the next one.
 * An empty label stays translatable; a saved label is the merchant's text.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_button_text = isset( $attributes['buttonText'] ) && is_string( $attributes['buttonText'] )
    ? $attributes['buttonText']
    : '';
$yaycheckout_label       = '' !== $yaycheckout_button_text ? $yaycheckout_button_text : __( 'Continue', 'yaycheckout' );

$yaycheckout_wrapper = get_block_wrapper_attributes(
    [
        'class' => 'yaycheckout-step-button',
        'type'  => 'button',
    ]
);

?>
<button <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>><?php echo esc_html( $yaycheckout_label ); ?></button>
