<?php
/**
 * Server render for yaycheckout/back-button.
 *
 * The steps script opens the previous step. On the first step the button is
 * hidden, and with firstStep "cart" a link to the cart shows in its place;
 * page load always opens step 1, so that is the state printed here, and the
 * steps script swaps the two as steps change. The link is the button's next
 * element, which is how the script pairs them. An empty label stays
 * translatable; a saved label is the merchant's text. The arrow is text
 * before the label on both, hidden from screen readers.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label      = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Back', 'yaycheckout' ) );
$yaycheckout_first_step = isset( $attributes['firstStep'] ) && 'hide' === $attributes['firstStep'] ? 'hide' : 'cart';
$yaycheckout_cart_url   = 'cart' === $yaycheckout_first_step && function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';

$yaycheckout_wrapper = get_block_wrapper_attributes(
    [
        'class'           => 'yaycheckout-back-button',
        'type'            => 'button',
        'data-first-step' => $yaycheckout_first_step,
    ]
);

$yaycheckout_cart_text = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'cartText', __( 'Return to cart', 'yaycheckout' ) );

// A layout saved before the setting has no value: no arrow.
$yaycheckout_icon      = \YayCheckout\Renderer\ButtonArrow::glyph( isset( $attributes['arrow'] ) ? (string) $attributes['arrow'] : 'none', 'back' );
$yaycheckout_icon_html = '' !== $yaycheckout_icon ? '<span class="yaycheckout-back-button__icon" aria-hidden="true">' . esc_html( $yaycheckout_icon ) . '</span>' : '';

?>
<button <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?> hidden><?php echo $yaycheckout_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it is built above. ?><?php echo esc_html( $yaycheckout_label ); ?></button>
<?php if ( '' !== $yaycheckout_cart_url ) : ?>
<a <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-back-button yaycheckout-back-button--cart' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?> href="<?php echo esc_url( $yaycheckout_cart_url ); ?>"><?php echo $yaycheckout_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it is built above. ?><?php echo esc_html( $yaycheckout_cart_text ); ?></a>
<?php endif; ?>
