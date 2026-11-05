<?php
/**
 * Server render for yaycheckout/order-again-button, on a taken-over thank-you page only.
 *
 * A form posting the order to OrderAgain, which puts its items back in the cart, without coupons,
 * and opens the checkout. Prints nothing when the shopper may not order this order again: an
 * account's order for anyone but its signed-in owner, or a status the filter leaves out. On a
 * preview link, the sample order shows a disabled button with no form. The block's style supports
 * go on the button, as on Place order.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_order = \YayCheckout\Pro\Renderer\ThankYouPage::get_instance()->order();

if ( null === $yaycheckout_order ) {
    return;
}

$yaycheckout_preview = null !== \YayCheckout\Renderer\PreviewRequest::get_instance()->layout_id();

if ( ! $yaycheckout_preview && ! \YayCheckout\Pro\Renderer\OrderAgain::can_reorder( $yaycheckout_order, $yaycheckout_order->get_order_key() ) ) {
    return;
}

$yaycheckout_label  = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Order again', 'yaycheckout' ) );
$yaycheckout_button = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-again wp-element-button' ] );

if ( $yaycheckout_preview ) {
    ?>
<button type="button" <?php echo $yaycheckout_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?> disabled><?php echo esc_html( $yaycheckout_label ); ?></button>
    <?php
    return;
}

?>
<form class="yaycheckout-order-again-form" method="post" action="<?php echo esc_url( wc_get_checkout_url() ); ?>">
<input type="hidden" name="<?php echo esc_attr( \YayCheckout\Pro\Renderer\OrderAgain::ACTION ); ?>" value="<?php echo esc_attr( (string) $yaycheckout_order->get_id() ); ?>" />
<input type="hidden" name="<?php echo esc_attr( \YayCheckout\Pro\Renderer\OrderAgain::KEY_FIELD ); ?>" value="<?php echo esc_attr( $yaycheckout_order->get_order_key() ); ?>" />
<?php wp_nonce_field( \YayCheckout\Pro\Renderer\OrderAgain::NONCE, '_wpnonce', false ); ?>
<button type="submit" <?php echo $yaycheckout_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>><?php echo esc_html( $yaycheckout_label ); ?></button>
</form>
