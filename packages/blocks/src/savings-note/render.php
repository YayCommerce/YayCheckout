<?php
/**
 * Prints the Savings note, which CheckoutFragments refreshes on AJAX totals updates.
 *
 * @var array    $attributes Block attributes (unused; print_savings_note reads $block).
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

\YayCheckout\Renderer\CheckoutFragments::print_savings_note( $block );
