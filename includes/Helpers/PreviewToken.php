<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless, expiring preview-link tokens: `base64url( "v1|blog|id|exp" ) . "." . hmac`.
 * Nothing is stored; the MAC is the only proof a link was minted here, so the
 * key is bound to this purpose and this blog (wp_salt alone is shared with
 * auth cookies). Per-link revocation would need a per-layout secret in the key.
 */
class PreviewToken {

    public const QUERY_ARG = 'yaycheckout_preview';

    private const VERSION = 'v1';

    /**
     * @return array{token: string, expires_at: int} expires_at is a Unix timestamp.
     */
    public static function mint( int $layout_id, int $ttl_minutes ): array {
        $expires_at = time() + $ttl_minutes * MINUTE_IN_SECONDS;
        $payload    = implode( '|', [ self::VERSION, get_current_blog_id(), $layout_id, $expires_at ] );
        $encoded    = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe transport, not obfuscation.

        return [
            'token'      => $encoded . '.' . self::mac( $payload ),
            'expires_at' => $expires_at,
        ];
    }

    /**
     * @return array{id: int, expires_at: int}|null Null for anything but a well-formed, unexpired token minted for this blog.
     */
    public static function parse( string $token ): ?array {
        $parts = explode( '.', $token, 2 );

        if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
            return null;
        }

        $payload = base64_decode( strtr( $parts[0], '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Strict: a malformed token is rejected here.

        if ( false === $payload || ! hash_equals( self::mac( $payload ), $parts[1] ) ) {
            return null;
        }

        $fields = explode( '|', $payload );

        if ( 4 !== count( $fields ) || self::VERSION !== $fields[0] || (int) $fields[1] !== get_current_blog_id() ) {
            return null;
        }

        $id         = (int) $fields[2];
        $expires_at = (int) $fields[3];

        if ( $id <= 0 || $expires_at <= time() ) {
            return null;
        }

        return [
            'id'         => $id,
            'expires_at' => $expires_at,
        ];
    }

    /** The shareable link: the checkout page carrying the token. */
    public static function checkout_url( string $token ): string {
        return self::with_token( wc_get_checkout_url(), $token );
    }

    /** Appends by hand: add_query_arg() re-encodes WooCommerce's %%endpoint%% placeholder in wc_ajax_url to %25%25. */
    public static function with_token( string $url, string $token ): string {
        return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . self::QUERY_ARG . '=' . rawurlencode( $token );
    }

    private static function mac( string $payload ): string {
        $key = hash_hmac( 'sha256', 'yaycheckout-preview|' . get_current_blog_id(), wp_salt( 'auth' ) );

        return hash_hmac( 'sha256', $payload, $key );
    }
}
