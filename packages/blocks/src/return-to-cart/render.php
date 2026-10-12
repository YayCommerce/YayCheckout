<?php
/**
 * Server render for yaycheckout/return-to-cart.
 *
 * A link to the cart, on every step. The URL is read at render time so it
 * follows WooCommerce's cart page. The wrapper is a block-level div so a
 * constrained layout can align it with the content; the link inside stays as
 * wide as its text. The icon is text, hidden from screen readers. An empty
 * label stays translatable; a saved label is the merchant's text.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Return to cart', 'yaycheckout' ) );
// A layout saved before the attribute existed has no value: the block.json default applies.
$yaycheckout_icon = \YayCheckout\Renderer\ButtonArrow::glyph( (string) ( $attributes['arrow'] ?? 'chevron' ), 'back' );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-return-to-cart' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
<a class="yaycheckout-return-to-cart__link" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
<?php if ( '' !== $yaycheckout_icon ) : ?>
<span class="yaycheckout-return-to-cart__icon" aria-hidden="true"><?php echo esc_html( $yaycheckout_icon ); ?></span>
<?php endif; ?>
<?php echo esc_html( $yaycheckout_label ); ?></a>
</div>
