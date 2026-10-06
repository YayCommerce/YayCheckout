<?php
/**
 * Server render for yaycheckout/login.
 *
 * Prints nothing unless the WooCommerce checkout login reminder is on and
 * the shopper is logged out. Attributes are read with defaults so markup
 * saved before an attribute existed still renders.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; login is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

if ( ! \YayCheckout\Renderer\CheckoutLogin::reminder_shown() ) {
    return;
}

$yaycheckout_text      = isset( $attributes['text'] ) && is_string( $attributes['text'] ) && '' !== $attributes['text']
    ? $attributes['text']
    : __( 'Returning customers? Log in', 'yaycheckout' );
$yaycheckout_link_text = isset( $attributes['linkText'] ) && is_string( $attributes['linkText'] ) ? $attributes['linkText'] : '';
$yaycheckout_parts     = \YayCheckout\Renderer\CheckoutLogin::split_link( $yaycheckout_text, $yaycheckout_link_text );
$yaycheckout_href      = function_exists( 'wc_get_checkout_url' ) ? wp_login_url( wc_get_checkout_url() ) : wp_login_url();
$yaycheckout_wrapper   = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-login' ] );

?>
<p <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo esc_html( $yaycheckout_parts[0] ); ?>
    <a class="yaycheckout-login__trigger" href="<?php echo esc_url( $yaycheckout_href ); ?>"><?php echo esc_html( $yaycheckout_parts[1] ); ?></a>
    <?php echo esc_html( $yaycheckout_parts[2] ); ?>
</p>
