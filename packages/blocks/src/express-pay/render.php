<?php
/**
 * Prints the wallet buttons from wc_get_pay_buttons() when there is a cart.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

unset( $content, $block );

$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;

// Buffered so an empty result marks the block empty for HideWhenEmpty.
ob_start();

if ( $yaycheckout_has_cart && function_exists( 'wc_get_pay_buttons' ) ) {
    wc_get_pay_buttons();
}

$yaycheckout_buttons = (string) ob_get_clean();

$yaycheckout_wrapper = \YayCheckout\Renderer\HideWhenEmpty::wrapper( [ 'class' => 'yaycheckout-express-pay' ], $attributes, '' === trim( $yaycheckout_buttons ) );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $yaycheckout_buttons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The gateways' own button markup, printed as wc_get_pay_buttons() would. ?>
</div>
