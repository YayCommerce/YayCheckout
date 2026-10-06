<?php
/**
 * Prints WooCommerce's <form name="checkout"> around the inner blocks, so
 * checkout.js and WC_Checkout see the stock form.
 *
 * @var array    $attributes
 * @var string   $content    Inner blocks, already rendered by core.
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_wrapper = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-checkout checkout woocommerce-checkout' ] );

if ( ! function_exists( 'WC' ) ) {
    ?>
    <div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
    </div>
    <?php
    return;
}

$yaycheckout_checkout = WC()->checkout();

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Verbatim from WooCommerce's form-checkout.php so its filter and translation still apply.
if ( ! $yaycheckout_checkout->is_registration_enabled() && $yaycheckout_checkout->is_registration_required() && ! is_user_logged_in() ) {
    echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
    return;
}

?>
<form <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?> name="checkout" method="post" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</form>
