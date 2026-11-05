<?php
/**
 * Server render for yaycheckout/thank-you-text.
 *
 * WordPress includes this file on each render and prints whatever it echoes.
 * The artwork and its colours are built in Pro\Renderer\ThankYouText.
 *
 * @var array    $attributes Block attributes: `variant`, `useThemeColors`, `mainColor`, `secondaryColor`, `maxWidth`, `alignment`.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

echo \YayCheckout\Pro\Renderer\ThankYouText::markup( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the wrapper attributes; the SVG is a plugin file.
