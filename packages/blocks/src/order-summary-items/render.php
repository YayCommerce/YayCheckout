<?php
/**
 * Prints the Items rows, which CheckoutFragments refreshes on AJAX totals updates.
 *
 * @var array    $attributes Block attributes; CheckoutFragments::order_items_options() reads them.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

\YayCheckout\Renderer\CheckoutFragments::print_summary_block( $block );
