<?php
/**
 * Prints the Items table, which CheckoutFragments refreshes on AJAX totals updates.
 *
 * @var array    $attributes Block attributes (none).
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php \YayCheckout\Renderer\CheckoutFragments::print_order_items(); ?>
</div>
