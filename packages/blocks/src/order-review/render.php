<?php
/**
 * Server render for yaycheckout/order-review: the #order_review container
 * WooCommerce fills with the order table, payment box and Place order.
 *
 * Without a frontend cart (admin, REST, CLI) the container stays empty:
 * WooCommerce's review-order template dereferences WC()->cart.
 *
 * @var array    $attributes Block attributes (none).
 * @var string   $content    Unused; order review is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

?>
<?php
$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own hooks, fired as form-checkout.php fires them so gateway and coupon plugins keep working.
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-review' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    if ( $yaycheckout_has_cart ) {
        do_action( 'woocommerce_checkout_before_order_review' );
    }
    ?>
    <div id="order_review" class="woocommerce-checkout-review-order">
        <?php
        if ( $yaycheckout_has_cart ) {
            do_action( 'woocommerce_checkout_order_review' );
        }
        ?>
    </div>
    <?php
    if ( $yaycheckout_has_cart ) {
        do_action( 'woocommerce_checkout_after_order_review' );
    }
    ?>
</div>
