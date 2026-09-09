<?php
/**
 * Server render for yaycheckout/checkout.
 *
 * Published layouts are stored as block markup and re-rendered on every
 * request, so every attribute must be read with a default: markup saved before
 * an attribute existed still has to render. That is what lets block attributes
 * be renamed without migrating merchant content.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-checkout' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
