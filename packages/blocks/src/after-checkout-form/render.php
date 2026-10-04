<?php
/**
 * Server render for yaycheckout/after-checkout-form.
 *
 * Fires WooCommerce's after-checkout-form action at this block's position.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

unset( $attributes, $content, $block );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-after-checkout-form' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php \YayCheckout\Helpers\HookBlockRunHelper::fire( \YayCheckout\Helpers\HookBlockRunHelper::AFTER_FORM ); ?>
</div>
