<?php
/**
 * Server render for yaycheckout/progress.
 *
 * WordPress includes this file on each render and prints whatever it echoes.
 * The list is built in CheckoutProgress from the layout's first Steps block.
 *
 * @var array    $attributes Block attributes. Unused; titles live on each step.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

echo \YayCheckout\Renderer\CheckoutProgress::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in CheckoutProgress::markup().
