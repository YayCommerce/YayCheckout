<?php
/**
 * Prints WooCommerce's billing and shipping addresses for the thank-you order, the same template
 * order-details.php loads after the order table. Wrapper holds block style supports. Prints nothing
 * when there is no validated order, or when WooCommerce would hide customer details (order user id
 * does not match the current user).
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_order = \YayCheckout\Renderer\ThankYouPage::get_instance()->order();

if ( null === $yaycheckout_order ) {
    return;
}

// Same gate as woocommerce/templates/order/order-details.php before order-details-customer.php.
if ( $yaycheckout_order->get_user_id() !== get_current_user_id() ) {
    return;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-customer-details' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    wc_get_template(
        'order/order-details-customer.php',
        [
            'order' => $yaycheckout_order,
        ]
    );
    ?>
</div>
