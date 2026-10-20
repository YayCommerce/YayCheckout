<?php
/**
 * Prints WooCommerce's billing and shipping addresses for the thank-you order, the same template
 * order-details.php loads after the order table. Optional titles from the block override WooCommerce's
 * gettext while the template prints. Wrapper holds block style supports. Prints nothing when there is
 * no validated order, or when WooCommerce would hide customer details (order user id does not match
 * the current user).
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

$yaycheckout_billing_title  = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'billingTitle' );
$yaycheckout_shipping_title = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'shippingTitle' );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-customer-details' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    \YayCheckout\Renderer\ThankYouPage::with_woocommerce_label_overrides(
        [
            'Billing address'  => $yaycheckout_billing_title,
            'Shipping address' => $yaycheckout_shipping_title,
        ],
        static function () use ( $yaycheckout_order ): void {
            wc_get_template(
                'order/order-details-customer.php',
                [
                    'order' => $yaycheckout_order,
                ]
            );
        }
    );
    ?>
</div>
