<?php
/**
 * Server render for yaycheckout/express-pay.
 *
 * Storefront hosts wallet buttons via wc_get_pay_buttons().
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

unset( $attributes, $content, $block );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-express-pay' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    if ( class_exists( '\YayCheckout\Renderer\CheckoutStorefront' ) && \YayCheckout\Renderer\CheckoutStorefront::is_storefront() ) {
        \YayCheckout\Renderer\CheckoutStorefront::print_express_pay();
    }
    ?>
</div>
