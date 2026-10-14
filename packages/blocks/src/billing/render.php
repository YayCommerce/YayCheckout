<?php
/**
 * Prints the Billing section: an optional heading and description, and its inner Field blocks.
 * woocommerce-billing-fields is the wrapper country-select.js searches for
 * #billing_state; without it the state box never repopulates.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_title       = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? $attributes['title'] : __( 'Billing', 'yaycheckout' );
$yaycheckout_show_title  = ! isset( $attributes['showTitle'] ) || (bool) $attributes['showTitle'];
$yaycheckout_heading_tag = \YayCheckout\Renderer\SectionTextStyles::heading_tag( $attributes );
$yaycheckout_description = trim( \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'description' ) );

// Block spacing.
$yaycheckout_class = 'yaycheckout-billing woocommerce-billing-fields' . \YayCheckout\Renderer\BlockGap::wrapper_class( $attributes, 'yaycheckout/billing', 'yaycheckout-billing' );

// Title and Description styles.
$yaycheckout_text_class = \YayCheckout\Renderer\SectionTextStyles::wrapper_class( $attributes, 'yaycheckout/billing' );
if ( '' !== $yaycheckout_text_class ) {
    $yaycheckout_class .= ' ' . $yaycheckout_text_class;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => $yaycheckout_class ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_show_title ) : ?>
        <<?php echo tag_escape( $yaycheckout_heading_tag ); ?> class="yaycheckout-section-title"><?php echo esc_html( $yaycheckout_title ); ?></<?php echo tag_escape( $yaycheckout_heading_tag ); ?>>
    <?php endif; ?>
    <?php if ( '' !== $yaycheckout_description ) : ?>
        <p class="yaycheckout-section-description"><?php echo esc_html( $yaycheckout_description ); ?></p>
    <?php endif; ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
