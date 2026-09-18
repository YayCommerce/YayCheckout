<?php
/**
 * Server render for yaycheckout/shipping.
 *
 * Inner Field blocks supply the address controls. Attributes are read with
 * defaults so markup saved before an attribute existed still renders.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_title      = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? $attributes['title'] : __( 'Shipping', 'yaycheckout' );
$yaycheckout_show_title = ! isset( $attributes['showTitle'] ) || (bool) $attributes['showTitle'];

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-shipping' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_show_title ) : ?>
        <h3><?php echo esc_html( $yaycheckout_title ); ?></h3>
    <?php endif; ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
