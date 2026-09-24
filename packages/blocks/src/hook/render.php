<?php
/**
 * Server render for yaycheckout/hook.
 *
 * Fires the selected WooCommerce checkout action at this block's position.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_hook = isset( $attributes['hook'] ) && is_string( $attributes['hook'] )
    ? sanitize_key( $attributes['hook'] )
    : '';

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-hook' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php \YayCheckout\Helpers\HookBlockRunHelper::fire( $yaycheckout_hook ); ?>
</div>
