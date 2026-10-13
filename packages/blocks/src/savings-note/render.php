<?php
/**
 * Prints the Savings note sentence, or an empty watchable wrapper while savings are zero.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_empty = \YayCheckout\Renderer\SavingsNote::is_zero();

?>
<div <?php echo \YayCheckout\Renderer\HideWhenEmpty::wrapper( [], $attributes, $yaycheckout_empty ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
<?php if ( ! $yaycheckout_empty ) : ?>
	<?php echo \YayCheckout\Renderer\SavingsNote::sentence_html( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sentence_html() escapes text and uses wc_price() HTML. ?>
<?php endif; ?>
</div>
