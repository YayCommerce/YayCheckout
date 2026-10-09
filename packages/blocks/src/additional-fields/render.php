<?php
/**
 * Prints the woocommerce-additional-fields part of WooCommerce's checkout/form-shipping.php, without
 * its heading: the order fieldset between the order-notes hooks. WooCommerce and plugins define the
 * fields and their required flags; WC_Checkout saves them.
 *
 * @var array    $attributes Unused; the block has no settings.
 * @var string   $content    Unused; the block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
    return;
}

$yaycheckout_checkout = WC()->checkout();
$yaycheckout_layout   = \YayCheckout\Renderer\CheckoutPage::get_instance()->layout();
// The Order note block prints order_comments; printing it twice would post the name twice.
$yaycheckout_skip_note = null !== $yaycheckout_layout && null !== \YayCheckout\Services\LayoutMarkupReader::order_note( $yaycheckout_layout->content );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-additional-fields woocommerce-additional-fields' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce hook before the order fields.
    do_action( 'woocommerce_before_order_notes', $yaycheckout_checkout );
    ?>

    <?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce filter. ?>
    <?php if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) : ?>

        <div class="woocommerce-additional-fields__field-wrapper">
            <?php foreach ( $yaycheckout_checkout->get_checkout_fields( 'order' ) as $yaycheckout_key => $yaycheckout_field ) : ?>
                <?php
                if ( $yaycheckout_skip_note && 'order_comments' === $yaycheckout_key ) {
                    continue;
                }

                woocommerce_form_field( $yaycheckout_key, $yaycheckout_field, $yaycheckout_checkout->get_value( $yaycheckout_key ) );
                ?>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <?php
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce hook after the order fields.
    do_action( 'woocommerce_after_order_notes', $yaycheckout_checkout );
    ?>
</div>
