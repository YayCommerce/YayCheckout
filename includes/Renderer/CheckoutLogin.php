<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * The WooCommerce "Enable log-in during checkout" option, and the sentence
 * split the login block prints.
 *
 * The option stays WooCommerce's; this class only reads it.
 */
class CheckoutLogin {

    public const OPTION_REMINDER = 'woocommerce_enable_checkout_login_reminder';

    public const DEFAULT_LINK_TEXT = 'Log in';

    /**
     * True when a logged-out shopper should see the reminder.
     */
    public static function reminder_shown(): bool {
        return 'yes' === get_option( self::OPTION_REMINDER ) && ! is_user_logged_in();
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
}
