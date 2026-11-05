<?php
/**
 * Server render for yaycheckout/save-pdf-button, on a taken-over thank-you page only.
 *
 * A button that opens the browser's print dialog (view.ts), and beside it, hidden, the order summary that
 * dialog prints (Pro\Renderer\OrderPrintSummary): only the sections turned on in the block, never the
 * page's other blocks. The button carries the PDF's default file name, Order-<order number>. Nothing runs
 * on the server when it is clicked, so it works on a preview link too, printing the sample order. The
 * block's style supports go on the button, as on Place order.
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

$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Save as PDF', 'yaycheckout' ) );
/* translators: %s: order number. The default file name of the saved PDF. */
$yaycheckout_filename = sprintf( __( 'Order-%s', 'yaycheckout' ), $yaycheckout_order->get_order_number() );
$yaycheckout_button   = get_block_wrapper_attributes(
    [
        'class'         => 'yaycheckout-save-pdf wp-element-button',
        'data-filename' => $yaycheckout_filename,
    ]
);

?>
<button type="button" <?php echo $yaycheckout_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>><?php echo esc_html( $yaycheckout_label ); ?></button>
<?php
echo \YayCheckout\Pro\Renderer\OrderPrintSummary::markup( $yaycheckout_order, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in OrderPrintSummary.
