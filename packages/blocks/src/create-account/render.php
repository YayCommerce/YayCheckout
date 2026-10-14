<?php
/**
 * Prints the account section of WooCommerce's form-billing.php. Account creation
 * stays in WC_Checkout::process_customer(); this block only supplies the fields.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; the block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) || is_user_logged_in() || ! WC()->checkout()->is_registration_enabled() ) {
    return;
}

$yaycheckout_checkout = WC()->checkout();

// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Verbatim WooCommerce string so the storefront matches classic checkout when the merchant leaves the label empty.
$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::rich_text( $attributes, 'checkboxLabel', __( 'Create an account?', 'woocommerce' ) );

$yaycheckout_wrapper = \YayCheckout\Renderer\BlockEmptiness::wrapper( [ 'class' => 'yaycheckout-create-account woocommerce-account-fields' ], $attributes );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( ! $yaycheckout_checkout->is_registration_required() ) : ?>

        <p class="form-row form-row-wide create-account">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( ( true === $yaycheckout_checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce filter. ?> type="checkbox" name="createaccount" value="1" /> <span><?php echo $yaycheckout_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtered by wp_kses in LayoutMarkupReader::rich_text(); the second sniff is for the WooCommerce filter earlier on this line, since only the last ignore on a line counts. ?></span>
            </label>
        </p>

    <?php endif; ?>

    <?php
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce hook around the account fields.
    do_action( 'woocommerce_before_checkout_registration_form', $yaycheckout_checkout );
    ?>

    <?php if ( $yaycheckout_checkout->get_checkout_fields( 'account' ) ) : ?>

        <div class="create-account">
            <?php foreach ( $yaycheckout_checkout->get_checkout_fields( 'account' ) as $yaycheckout_key => $yaycheckout_field ) : ?>
                <?php woocommerce_form_field( $yaycheckout_key, $yaycheckout_field, $yaycheckout_checkout->get_value( $yaycheckout_key ) ); ?>
            <?php endforeach; ?>
            <div class="clear"></div>
        </div>

    <?php endif; ?>

    <?php
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Verbatim WooCommerce hook around the account fields.
    do_action( 'woocommerce_after_checkout_registration_form', $yaycheckout_checkout );
    ?>
</div>
