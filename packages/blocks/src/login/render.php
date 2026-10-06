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
$yaycheckout_behavior  = isset( $attributes['behavior'] ) && 'page' === $attributes['behavior'] ? 'page' : 'modal';
$yaycheckout_parts     = \YayCheckout\Renderer\CheckoutLogin::split_link( $yaycheckout_text, $yaycheckout_link_text );
$yaycheckout_href      = function_exists( 'wc_get_checkout_url' ) ? wp_login_url( wc_get_checkout_url() ) : wp_login_url();
$yaycheckout_login_url = class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'yaycheckout_login' ) : '';
$yaycheckout_state_url = class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'yaycheckout_login_state' ) : '';
$yaycheckout_wrapper   = get_block_wrapper_attributes(
    [
        'class'          => 'yaycheckout-login',
        'data-behavior'  => $yaycheckout_behavior,
        'data-login-url' => $yaycheckout_login_url,
        'data-state-url' => $yaycheckout_state_url,
    ]
);

?>
<p <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo esc_html( $yaycheckout_parts[0] ); ?>
    <?php if ( 'page' === $yaycheckout_behavior ) : ?>
        <a class="yaycheckout-login__trigger" href="<?php echo esc_url( $yaycheckout_href ); ?>"><?php echo esc_html( $yaycheckout_parts[1] ); ?></a>
    <?php else : ?>
        <button type="button" class="yaycheckout-login__trigger" aria-haspopup="dialog"><?php echo esc_html( $yaycheckout_parts[1] ); ?></button>
        <template class="yaycheckout-login__template">
            <dialog class="yaycheckout-login__dialog" aria-labelledby="yaycheckout-login-title">
                <button type="button" class="yaycheckout-login__close" aria-label="<?php esc_attr_e( 'Close', 'yaycheckout' ); ?>">&times;</button>
                <form method="post">
                    <h2 id="yaycheckout-login-title"><?php esc_html_e( 'Log in', 'yaycheckout' ); ?></h2>
                    <input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( \YayCheckout\Renderer\CheckoutLogin::NONCE_ACTION ) ); ?>" />
                    <?php
                    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- WooCommerce login form hooks and strings, so plugins and existing translations apply.
                    do_action( 'woocommerce_login_form_start' );
                    ?>
                    <p class="form-row">
                        <label for="yaycheckout-login-username"><?php esc_html_e( 'Username or email', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                        <input type="text" class="input-text" name="username" id="yaycheckout-login-username" autocomplete="username" required />
                    </p>
                    <p class="form-row">
                        <label for="yaycheckout-login-password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                        <input type="password" class="input-text" name="password" id="yaycheckout-login-password" autocomplete="current-password" required />
                    </p>
                    <?php do_action( 'woocommerce_login_form' ); ?>
                    <p class="form-row">
                        <label>
                            <input type="checkbox" name="rememberme" value="forever" />
                            <?php esc_html_e( 'Remember me', 'woocommerce' ); ?>
                        </label>
                    </p>
                    <?php do_action( 'woocommerce_login_form_end' ); ?>
                    <p class="yaycheckout-login__error" role="alert" hidden></p>
                    <button type="submit" class="button yaycheckout-login__submit"><?php esc_html_e( 'Log in', 'yaycheckout' ); ?></button>
                    <p class="lost_password">
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
                    </p>
                    <?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch ?>
                </form>
            </dialog>
        </template>
    <?php endif; ?>
    <?php echo esc_html( $yaycheckout_parts[2] ); ?>
</p>
