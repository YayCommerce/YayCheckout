<?php
/**
 * Prints the Coupon Form's Apply button. The button is the block's root element, so its style
 * supports land on it. assets/js/checkout-coupon-form.js finds it by yaycheckout-coupon-form__apply.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_button_text = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Apply coupon', 'yaycheckout' ) );
$yaycheckout_wrapper     = get_block_wrapper_attributes(
    [ 'class' => \YayCheckout\Helpers\ButtonClassHelper::classes( 'button' ) . ' yaycheckout-coupon-form__apply' ]
);

?>
<button type="button" <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>><?php echo esc_html( $yaycheckout_button_text ); ?></button>
