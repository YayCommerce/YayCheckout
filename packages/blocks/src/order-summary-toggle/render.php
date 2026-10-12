<?php
/**
 * Prints the toggle's blocks in core's flex row. While the Order Summary is collapsible, the row is a
 * button that opens and closes its details (order-summary/view.ts), with a chevron after the blocks;
 * otherwise it prints them plainly.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_collapse = \YayCheckout\Services\OrderSummaryCollapse::of_layout( \YayCheckout\Renderer\CheckoutPage::get_instance()->layout() );

$yaycheckout_extra = '';
$yaycheckout_inner = $content;

if ( $yaycheckout_collapse['collapsible'] ) {
    // A link inside role="button" would be a control inside a control, so links print as their text.
    $yaycheckout_allowed = wp_kses_allowed_html( 'post' );
    unset( $yaycheckout_allowed['a'] );
    // Core's Image prints responsive sources that the post rules leave out.
    $yaycheckout_allowed['img'] = array_merge(
        $yaycheckout_allowed['img'] ?? [],
        [
            'srcset' => true,
            'sizes'  => true,
        ]
    );

    $yaycheckout_extra = sprintf(
        ' role="button" tabindex="0" aria-expanded="%s" aria-controls="%s" data-yaycheckout-summary-toggle',
        $yaycheckout_collapse['open'] ? 'true' : 'false',
        esc_attr( \YayCheckout\Services\OrderSummaryCollapse::DETAILS_ID )
    );
    $yaycheckout_inner = wp_kses( $content, $yaycheckout_allowed );

    if ( false !== ( $attributes['showChevron'] ?? true ) ) {
        $yaycheckout_inner .= \YayCheckout\Services\Disclosure::chevron( 'yaycheckout-order-summary-toggle__chevron' );
    }
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-summary-toggle' ] ) . $yaycheckout_extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes its attribute string, and the extra attributes are built escaped above. ?>>
    <?php echo $yaycheckout_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, escaped by core, then through kses while collapsible. ?>
</div>
