<?php
/**
 * Server render for yaycheckout/steps-progress.
 *
 * WordPress includes this file on each render and prints whatever it echoes.
 * The list is built in CheckoutStepsProgress from the layout's first Steps block.
 *
 * @var array    $attributes Block attributes: `variant`, `showCount`, `activeColor`, `inactiveColor`, `disabledTextColor`. Titles live on each step.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

echo \YayCheckout\Renderer\CheckoutStepsProgress::markup( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in CheckoutStepsProgress::markup().
