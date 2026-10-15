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

$yaycheckout_layout_id = '0';
$yaycheckout_class     = 'yaycheckout-checkout checkout woocommerce-checkout';
$yaycheckout_layout    = \YayCheckout\Renderer\CheckoutPage::get_instance()->layout();

if ( null !== $yaycheckout_layout ) {
    $yaycheckout_layout_id = (string) $yaycheckout_layout->id;

    if ( $yaycheckout_layout->design->has_floating_labels() ) {
        $yaycheckout_class .= ' yaycheckout-checkout--floating-labels';
    } elseif ( $yaycheckout_layout->design->has_hidden_labels() ) {
        $yaycheckout_class .= ' yaycheckout-checkout--hidden-labels';
    }
}

$yaycheckout_wrapper = get_block_wrapper_attributes(
    [
        'class'                   => $yaycheckout_class,
        'data-yaycheckout-layout' => $yaycheckout_layout_id,
    ]
);

// No form: without WooCommerce, or in the merchant's logged-out view, whose blocks replace WooCommerce's login prompt.
if ( ! function_exists( 'WC' ) || \YayCheckout\Renderer\CheckoutPage::get_instance()->is_logged_out_view() ) {
    ?>
    <div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
    </div>
    <?php
    return;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Verbatim from WooCommerce's form-checkout.php so its filter and translation still apply.
if ( \YayCheckout\Renderer\CheckoutLogin::must_log_in() ) {
    if ( \YayCheckout\Renderer\CheckoutLogin::reminder_shown() ) {
        $yaycheckout_login = null;

        if ( $block instanceof WP_Block ) {
            foreach ( $block->inner_blocks as $yaycheckout_inner ) {
                if ( $yaycheckout_inner instanceof WP_Block && 'yaycheckout/login' === $yaycheckout_inner->name ) {
                    $yaycheckout_login = $yaycheckout_inner;
                    break;
                }
            }
        }

        if ( ! $yaycheckout_login instanceof WP_Block ) {
            $yaycheckout_login = new WP_Block(
                array(
                    'blockName'   => 'yaycheckout/login',
                    'attrs'       => array(),
                    'innerBlocks' => array(),
                )
            );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The login block escapes its own markup.
        echo $yaycheckout_login->render();
    }

    echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
    return;
}

?>
<form <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?> name="checkout" method="post" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</form>
