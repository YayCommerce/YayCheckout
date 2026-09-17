<?php
/**
 * Read every attribute with a default: markup saved before an attribute
 * existed must still render.
 *
 * @var array    $attributes
 * @var string   $content    Inner blocks, already rendered by core.
 * @var WP_Block $block
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-checkout' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
