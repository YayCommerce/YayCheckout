<?php
/**
 * Prints what plugins add on the chosen WooCommerce checkout hook, which then fires here and no longer
 * in the block that fires it otherwise. A hook update_order_review reprints always gets its root, empty
 * or not, so the refresh has a target; any other hook prints nothing while nothing hooks into it.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

unset( $content, $block );

$yaycheckout_hook = isset( $attributes['hook'] ) && is_string( $attributes['hook'] ) ? $attributes['hook'] : '';

if ( ! function_exists( 'WC' ) || ! \YayCheckout\Renderer\WcHooks::is_allowed( $yaycheckout_hook ) ) {
    return;
}

$yaycheckout_output = \YayCheckout\Renderer\WcHooks::fire( $yaycheckout_hook );
$yaycheckout_empty  = '' === trim( $yaycheckout_output );
$yaycheckout_base   = [
    'class'     => 'yaycheckout-wc-hook',
    'data-hook' => $yaycheckout_hook,
];

if ( \YayCheckout\Renderer\WcHooks::is_refreshed( $yaycheckout_hook ) ) {
    $yaycheckout_inner = \YayCheckout\Renderer\WcHooks::content_root( $yaycheckout_hook, $yaycheckout_output );
} elseif ( $yaycheckout_empty ) {
    // Prints nothing, so the merchant's padding, border or background don't draw an empty box. BlockEmptiness counts it as empty.
    return;
} else {
    $yaycheckout_inner = \YayCheckout\Renderer\CheckoutFragments::wrap_extension_html( $yaycheckout_output, \YayCheckout\Renderer\WcHooks::ROWS_CLASS );
}

$yaycheckout_wrapper = \YayCheckout\Renderer\BlockEmptiness::wrapper( $yaycheckout_base, $attributes, $yaycheckout_empty );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $yaycheckout_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Whatever the hook's callbacks print, as do_action() would. ?>
</div>
