<?php
/**
 * Server render for yaycheckout/place-order.
 *
 * Storefront prints the classic submit button, terms, and process-checkout nonce.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_button_text = isset( $attributes['buttonText'] ) && is_string( $attributes['buttonText'] )
    ? $attributes['buttonText']
    : '';

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-place-order' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    if ( class_exists( '\YayCheckout\Renderer\CheckoutStorefront' ) && \YayCheckout\Renderer\CheckoutStorefront::is_storefront() ) {
        \YayCheckout\Renderer\CheckoutStorefront::print_place_order( $yaycheckout_button_text );
    } else {
        $yaycheckout_label = '' !== $yaycheckout_button_text ? $yaycheckout_button_text : __( 'Place order', 'yaycheckout' );
        echo '<button type="button" class="button alt" disabled>' . esc_html( $yaycheckout_label ) . '</button>';
    }
    ?>
</div>
