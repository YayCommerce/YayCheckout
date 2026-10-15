<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout login for the login block: the WooCommerce reminder option, the
 * sentence split, and the two wc-ajax calls the modal uses.
 *
 * Login sets the auth cookie. The following request, login_state, is the
 * first one that can see the logged-in customer.
 */
class CheckoutLogin {

    use SingletonTrait;

    public const OPTION_REMINDER = 'woocommerce_enable_checkout_login_reminder';

    public const DEFAULT_LINK_TEXT = 'Log in';

    public const TRIGGER_CLASS = 'yaycheckout-login__trigger';

    public const NONCE_ACTION = 'yaycheckout-login';

    protected function __construct() {
        // wc-ajax serves guests and logged-in shoppers on the same hook.
        add_action( 'wc_ajax_yaycheckout_login', [ $this, 'login' ] );
        add_action( 'wc_ajax_yaycheckout_login_state', [ $this, 'state' ] );
    }

    /**
     * True when a logged-out shopper should see the reminder.
     */
    public static function reminder_shown(): bool {
        return self::reminder_enabled() && ! is_user_logged_in();
    }

    /** WooCommerce's "log in at checkout" reminder setting. */
    public static function reminder_enabled(): bool {
        return 'yes' === get_option( self::OPTION_REMINDER );
    }

    /**
     * A guest WooCommerce gives no checkout form: registration is required and not offered at checkout.
     * Mirrors form-checkout.php and woocommerce_checkout_coupon_form().
     */
    public static function must_log_in(): bool {
        return ! is_user_logged_in() && self::login_required();
    }

    /** WooCommerce's settings make a guest log in first: no guest checkout, and no account creation at checkout. False without WooCommerce. */
    public static function login_required(): bool {
        if ( ! function_exists( 'WC' ) ) {
            return false;
        }

        $checkout = WC()->checkout();

        return ! $checkout->is_registration_enabled() && $checkout->is_registration_required();
    }

    /**
     * Split a sentence around the first occurrence of the link phrase.
     *
     * A missing or empty phrase is appended, so a translation that drops the
     * phrase still offers a control.
     *
     * @param string $text      The full sentence.
     * @param string $link_text Phrase that receives the click. Empty uses the default.
     * @return array{0: string, 1: string, 2: string} Before, link, after.
     */
    public static function split_link( string $text, string $link_text ): array {
        $link = '' !== $link_text ? $link_text : self::DEFAULT_LINK_TEXT;
        $at   = strpos( $text, $link );

        if ( '' === $link_text || false === $at ) {
            return array( $text . ' ', $link, '' );
        }

        return array(
            substr( $text, 0, $at ),
            $link,
            substr( $text, $at + strlen( $link ) ),
        );
    }

    /**
     * Split a sentence that marks its click control with a trigger span.
     *
     * Returns before, trigger and after as decoded plain text. An empty array
     * means the HTML has no non-empty trigger span.
     *
     * @param string $html Sentence that may contain a trigger span.
     * @return array{0: string, 1: string, 2: string}|array{}
     */
    public static function trigger_parts( string $html ): array {
        $clean = wp_kses(
            $html,
            array(
                'span' => array(
                    'class' => true,
                ),
            )
        );

        if ( '' === $clean ) {
            return array();
        }

        $dom      = new \DOMDocument();
        $previous = libxml_use_internal_errors( true );
        // A full document keeps UTF-8 text; the fragment alone can corrupt it.
        $dom->loadHTML( '<?xml encoding="utf-8" ?><div id="yaycheckout-login-root">' . $clean . '</div>' );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        $root = $dom->getElementById( 'yaycheckout-login-root' );

        if ( ! $root instanceof \DOMElement ) {
            return array();
        }

        $trigger = null;

        // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument property names.
        foreach ( $root->getElementsByTagName( 'span' ) as $span ) {
            if ( ! $span instanceof \DOMElement ) {
                continue;
            }

            $classes = preg_split( '/\s+/', $span->getAttribute( 'class' ), -1, PREG_SPLIT_NO_EMPTY );

            if ( ! is_array( $classes ) || ! in_array( self::TRIGGER_CLASS, $classes, true ) ) {
                continue;
            }

            if ( '' === $span->textContent ) {
                continue;
            }

            $trigger = $span;
            break;
        }

        if ( ! $trigger instanceof \DOMElement ) {
            return array();
        }

        $before = '';
        $after  = '';
        $seen   = false;

        foreach ( $root->childNodes as $child ) {
            if ( $child->isSameNode( $trigger ) ) {
                $seen = true;
                continue;
            }

            $piece = $child->textContent;

            if ( $seen ) {
                $after .= $piece;
            } else {
                $before .= $piece;
            }
        }

        $parts = array( $before, $trigger->textContent, $after );
        // phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

        return $parts;
    }

    /**
     * Sign the shopper in. Mirrors WC_Form_Handler::process_login() without
     * the redirect, because the modal stays on the checkout page.
     *
     * @throws \Exception Login failed. Caught here and returned as JSON.
     */
    public function login(): void {
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Same hooks and strings as WC_Form_Handler::process_login().
        if ( ! check_ajax_referer( self::NONCE_ACTION, 'security', false ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'We could not verify the login request. Please refresh and try again.', 'yaycheckout' ),
                )
            );
        }

        if ( is_user_logged_in() ) {
            wp_send_json_success();
        }

        $username = isset( $_POST['username'] ) && is_string( $_POST['username'] ) ? trim( wp_unslash( $_POST['username'] ) ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $password = isset( $_POST['password'] ) && is_string( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        if ( ! is_string( $username ) || ! is_string( $password ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Username is required.', 'woocommerce' ),
                )
            );
        }

        try {
            $creds = array(
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => isset( $_POST['rememberme'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            );

            $validation_error = new \WP_Error();
            $validation_error = apply_filters( 'woocommerce_process_login_errors', $validation_error, $creds['user_login'], $creds['user_password'] );

            if ( $validation_error->get_error_code() ) {
                throw new \Exception( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> ' . $validation_error->get_error_message() );
            }

            if ( '' === $creds['user_login'] ) {
                throw new \Exception( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> ' . __( 'Username is required.', 'woocommerce' ) );
            }

            $user = wp_signon( apply_filters( 'woocommerce_login_credentials', $creds ), is_ssl() );

            if ( is_wp_error( $user ) ) {
                throw new \Exception( $user->get_error_message() );
            }

            if ( is_multisite() && ! is_user_member_of_blog( $user->ID, get_current_blog_id() ) ) {
                add_user_to_blog( get_current_blog_id(), $user->ID, 'customer' );
            }
        } catch ( \Exception $error ) {
            do_action( 'woocommerce_login_failed' );
            wp_send_json_error(
                array(
                    'message' => wp_strip_all_tags( (string) apply_filters( 'login_errors', $error->getMessage() ) ),
                )
            );
        }

        wp_send_json_success();
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch
    }

    /**
     * Account field values and fresh checkout nonces, after the login cookie
     * is on the request.
     */
    public function state(): void {
        if ( ! is_user_logged_in() || ! function_exists( 'WC' ) ) {
            wp_send_json_error( null, 401 );
        }

        $layout    = CheckoutPage::get_instance()->layout();
        $layout_id = 0;
        $values    = array();

        if ( null !== $layout ) {
            $layout_id = (int) $layout->id;
            $fields    = LayoutMarkupReader::fields( $layout->content );

            if ( ! isset( $fields['billing_email'] ) ) {
                $fields['billing_email'] = array( 'type' => 'email' );
            }

            $checkout = WC()->checkout();

            foreach ( $fields as $key => $field ) {
                if ( isset( $field['type'] ) && 'password' === $field['type'] ) {
                    continue;
                }

                $value = $checkout->get_value( $key );

                if ( ! is_scalar( $value ) || '' === (string) $value ) {
                    continue;
                }

                $values[ $key ] = (string) $value;
            }
        }

        wp_send_json_success(
            array(
                'layout_id' => $layout_id,
                'values'    => $values,
                'nonces'    => array(
                    'update_order_review' => wp_create_nonce( 'update-order-review' ),
                    'process_checkout'    => wp_create_nonce( 'woocommerce-process_checkout' ),
                ),
            )
        );
    }
}
