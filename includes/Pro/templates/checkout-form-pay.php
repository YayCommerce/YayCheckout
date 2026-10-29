<?php
/**
 * Pro: our checkout/form-pay.php while PayOrderPage takes over: WooCommerce includes it from order_pay() once the
 * shopper may pay this order. Prints the layout's pay-order blocks inside WooCommerce's #order_review form, with
 * the fields WooCommerce's pay_action() reads. Markup without a Checkout block, which Payment, Terms and
 * Place order need around them for their styles, is wrapped in one; one without a Payment or Place order
 * block gets the block's default output inside it, so the shopper can always pay.
 *
 * @var WC_Order             $order              The order to pay, from order_pay().
 * @var WC_Payment_Gateway[] $available_gateways Its available gateways.
 * @var string               $order_button_text  The pay button's text.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_pay_page = \YayCheckout\Pro\Renderer\PayOrderPage::get_instance();
$yaycheckout_layout   = $yaycheckout_pay_page->layout();

if ( null === $yaycheckout_layout ) {
    return;
}

$yaycheckout_pay_page->begin_form( $order, (array) $available_gateways, (string) $order_button_text );

$yaycheckout_markup  = $yaycheckout_layout->content;
$yaycheckout_missing = '';

foreach ( [ 'yaycheckout/payment', 'yaycheckout/place-order' ] as $yaycheckout_required_block ) {
    if ( ! has_block( $yaycheckout_required_block, $yaycheckout_markup ) ) {
        $yaycheckout_missing .= "\n<!-- wp:" . $yaycheckout_required_block . ' /-->';
    }
}

if ( ! has_block( 'yaycheckout/checkout', $yaycheckout_markup ) ) {
    $yaycheckout_markup = "<!-- wp:yaycheckout/checkout -->\n" . $yaycheckout_markup . $yaycheckout_missing . "\n<!-- /wp:yaycheckout/checkout -->";
} elseif ( '' !== $yaycheckout_missing ) {
    // Into the last Checkout, so the added blocks get its styles.
    $yaycheckout_close  = '<!-- /wp:yaycheckout/checkout -->';
    $yaycheckout_at     = strrpos( $yaycheckout_markup, $yaycheckout_close );
    $yaycheckout_markup = false === $yaycheckout_at
        ? $yaycheckout_markup . $yaycheckout_missing
        : substr( $yaycheckout_markup, 0, $yaycheckout_at ) . $yaycheckout_missing . "\n" . substr( $yaycheckout_markup, $yaycheckout_at );
}
?>
<form id="order_review" method="post" class="yaycheckout-pay-order">
    <?php
    // finalize() decides the blocks whose visibility depends on a block rendered after them.
    echo \YayCheckout\Renderer\BlockVisibility::get_instance()->finalize( \YayCheckout\Renderer\LayoutShortcodes::render( $yaycheckout_markup ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, rendered and escaped by each block.
    ?>
    <input type="hidden" name="woocommerce_pay" value="1" />
    <?php wp_nonce_field( 'woocommerce-pay', 'woocommerce-pay-nonce' ); ?>
</form>
<?php
$yaycheckout_pay_page->end_form();
