<?php
/**
 * Prints what the order's payment method adds on woocommerce_thankyou_<gateway>, such as Direct bank
 * transfer's bank details, here in the layout instead of after it. ThankYouPage fires the hook once per
 * request, so the thank-you template's fallback after the layout stays silent once this block printed.
 * The block's title and labels replace Direct bank transfer's (PaymentInstructions), and Show title off
 * drops its heading. The wrapper carries OrderPrintSummary::GATEWAY_CLASS, so the Save as PDF Button copies
 * the printed instructions from it. Prints nothing when the gateway prints nothing, so the merchant's padding or border
 * draw no empty box.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

unset( $content, $block );

$yaycheckout_instructions = \YayCheckout\Pro\Renderer\ThankYouPage::get_instance()->payment_instructions(
    static function ( callable $fire ) use ( $attributes ): void {
        \YayCheckout\Pro\Renderer\PaymentInstructions::with_labels( $attributes, $fire );
    }
);

if ( ! \YayCheckout\Pro\Renderer\PaymentInstructions::shows_title( $attributes ) ) {
    $yaycheckout_instructions = \YayCheckout\Pro\Renderer\PaymentInstructions::without_title( $yaycheckout_instructions );
}

if ( '' === trim( $yaycheckout_instructions ) ) {
    return;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-payment-instructions ' . \YayCheckout\Pro\Renderer\OrderPrintSummary::GATEWAY_CLASS ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $yaycheckout_instructions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Whatever the gateway's callbacks print, as do_action() would. ?>
</div>
