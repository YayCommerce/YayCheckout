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

$yaycheckout_wrapper = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-summary-toggle' ] );

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

?>
<?php if ( $yaycheckout_collapse['collapsible'] ) : ?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?> role="button" tabindex="0" aria-expanded="<?php echo $yaycheckout_collapse['open'] ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( \YayCheckout\Services\OrderSummaryCollapse::DETAILS_ID ); ?>" data-yaycheckout-summary-toggle>
    <?php echo wp_kses( $content, $yaycheckout_allowed ); ?>
    <?php if ( false !== ( $attributes['showChevron'] ?? true ) ) : ?>
        <?php echo \YayCheckout\Services\Disclosure::chevron( 'yaycheckout-order-summary-toggle__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup; its class is escaped. ?>
    <?php endif; ?>
</div>
<?php else : ?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
<?php endif; ?>
