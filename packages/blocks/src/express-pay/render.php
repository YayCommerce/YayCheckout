<?php
/**
 * Prints the wallet buttons from wc_get_pay_buttons() when there is a cart.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

unset( $attributes, $content, $block );

$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-express-pay' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    if ( $yaycheckout_has_cart && function_exists( 'wc_get_pay_buttons' ) ) {
        wc_get_pay_buttons();
    }
    ?>
</div>
