<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Pins the layout a checkout page load picked from the cart, so the form's AJAX calls and its submit
 * keep it while the shopper changes the cart. Used only while the active set reads the cart.
 *
 * The WooCommerce session holds the pin. A signed cookie mirrors it as a hint for ThemelessCheckout,
 * which decides at setup_theme, before the session loads; it chooses the theme only, never the layout.
 */
class LayoutPin {

    /** WC session key: the pinned layout id, 0 when the page load picked none. */
    public const SESSION_KEY = 'yaycheckout_layout_pin';

    /** Theme-hint cookie: "{id}.{mac}". */
    public const COOKIE = 'yaycheckout_layout_pin';

    /** Marks the reload that corrects a wrong theme guess, so blocked cookies can't loop it. */
    public const REPICK_ARG = 'yaycheckout_repick';

    /**
     * A checkout page load: picks from the shopper and the cart, then pins the pick.
     *
     * @param array $shopper CheckoutPage::shopper_context().
     */
    public static function pick( LayoutRepository $layouts, array $shopper ): ?Layout {
        $layout = $layouts->active( $shopper + CartFacts::context() );
        $id     = null === $layout ? 0 : $layout->id;

        if ( null !== WC()->session ) {
            WC()->session->set( self::SESSION_KEY, $id );
        }

        // Session-length and httponly: only the next request's setup_theme reads it.
        wc_setcookie( self::COOKIE, $id . '.' . self::mac( $id ), 0, is_ssl(), true );

        return $layout;
    }

    /**
     * Any other request: the pinned layout while it is still active, none when the page load picked none.
     * Without a usable pin (no session, or the layout left the active set) it picks afresh, unpinned.
     *
     * @param array $shopper CheckoutPage::shopper_context().
     */
    public static function pinned( LayoutRepository $layouts, array $shopper ): ?Layout {
        $id = null !== WC()->session ? WC()->session->get( self::SESSION_KEY ) : null;

        if ( 0 === $id ) {
            return null;
        }

        if ( is_int( $id ) && $layouts->is_active( $id ) ) {
            return $layouts->find( $id );
        }

        return $layouts->active( $shopper + CartFacts::context() );
    }

    /** The id the cookie hints, 0 for none; null when it is missing or not signed here. Readable at setup_theme. */
    public static function hint(): ?int {
        $cookie = isset( $_COOKIE[ self::COOKIE ] ) && is_string( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
        $parts  = explode( '.', $cookie, 2 );

        if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! hash_equals( self::mac( (int) $parts[0] ), $parts[1] ) ) {
            return null;
        }

        return (int) $parts[0];
    }

    /** A GET that is not a wc-ajax call: the request that renders a page. Readable at setup_theme. */
    public static function is_page_request(): bool {
        $method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

        return 'get' === $method && ! isset( $_GET['wc-ajax'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
    }

    /** Keyed to this purpose and blog: wp_salt alone is shared with auth cookies. */
    private static function mac( int $id ): string {
        $key = hash_hmac( 'sha256', 'yaycheckout-layout-pin|' . get_current_blog_id(), wp_salt( 'auth' ) );

        return hash_hmac( 'sha256', (string) $id, $key );
    }
}
