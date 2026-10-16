<?php
/**
 * Prints the wallet buttons that gateways add on ExpressPay::HOOK when there is a cart.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

unset( $content, $block );

$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;

// Buffered so an empty result prints nothing.
ob_start();

if ( $yaycheckout_has_cart ) {
    \YayCheckout\Renderer\WcHooks::in_place( \YayCheckout\Renderer\ExpressPay::HOOK );
    \YayCheckout\Renderer\WcHooks::in_place( \YayCheckout\Renderer\ExpressPay::AFTER_HOOK );
}

$yaycheckout_buttons = (string) ob_get_clean();

// Prints nothing without buttons, so the merchant's padding, border or background don't draw an empty box.
// BlockEmptiness counts a block that prints nothing as empty.
if ( '' === trim( $yaycheckout_buttons ) ) {
    return;
}

// Empty until a gateway's script shows a button: gateways print hidden containers and fill them after load.
// view.ts clears the marker then, and style.css hides the block while it is set, so it is printed even
// without a blockId, which BlockEmptiness needs to print it.
$yaycheckout_wrapper = \YayCheckout\Renderer\BlockEmptiness::wrapper(
    [
        'class'                                          => 'yaycheckout-express-pay',
        \YayCheckout\Renderer\BlockEmptiness::EMPTY_ATTR => '1',
    ],
    $attributes
);

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $yaycheckout_buttons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The gateways' own button markup, printed as the classic checkout would. ?>
</div>
