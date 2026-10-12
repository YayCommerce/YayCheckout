<?php
/**
 * Server render for yaycheckout/go-to-step.
 *
 * Prints nothing when the layout has no step at that position. The button starts
 * disabled: on load step 1 is open and no step is complete. The view script enables
 * it once its step is complete and asks the steps script to reopen that step.
 * An empty label stays translatable; a saved label is the merchant's text.
 *
 * @var array    $attributes Block attributes: `step` (1-based position in the first Steps block), `buttonText`.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_step = (int) ( $attributes['step'] ?? 1 );

if ( $yaycheckout_step < 1 || $yaycheckout_step > count( \YayCheckout\Renderer\CheckoutProgress::layout_titles() ) ) {
    return;
}

$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Edit', 'yaycheckout' ) );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-go-to-step' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
<button type="button" class="yaycheckout-go-to-step__button" data-step="<?php echo esc_attr( (string) $yaycheckout_step ); ?>" disabled><?php echo esc_html( $yaycheckout_label ); ?></button>
</div>
