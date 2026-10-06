<?php
/**
 * Server render for yaycheckout/back-button.
 *
 * The steps script opens the previous step. An empty label stays
 * translatable; a saved label is the merchant's text.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'buttonText', __( 'Back', 'yaycheckout' ) );

$yaycheckout_wrapper = get_block_wrapper_attributes(
    [
        'class' => 'yaycheckout-back-button',
        'type'  => 'button',
    ]
);

?>
<button <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>><?php echo esc_html( $yaycheckout_label ); ?></button>
