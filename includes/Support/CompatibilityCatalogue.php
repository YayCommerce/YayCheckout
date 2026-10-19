<?php
namespace YayCheckout\Support;

defined( 'ABSPATH' ) || exit;

/**
 * What YayCheckout knows about gateways and plugins at checkout: the status
 * vocabulary, the bundled list every install ships with, and normalize(),
 * the only gate the remote catalogue passes through before reaching the SPA.
 * Pure on purpose — the repository owns fetching and caching.
 */
final class CompatibilityCatalogue {

    public const STATUS_VERIFIED   = 'verified';
    public const STATUS_COMMUNITY  = 'community';
    public const STATUS_UNVERIFIED = 'unverified';
    public const STATUS_BLOCK_ONLY = 'block-only';

    public const GATEWAY_STATUSES = [ self::STATUS_VERIFIED, self::STATUS_COMMUNITY, self::STATUS_UNVERIFIED, self::STATUS_BLOCK_ONLY ];
    public const PLUGIN_STATUSES  = [ self::STATUS_VERIFIED, self::STATUS_COMMUNITY, self::STATUS_UNVERIFIED ];

    public const SOURCE_REMOTE  = 'remote';
    public const SOURCE_BUNDLED = 'bundled';

    /** Transient holding the last fetched (or fallen-back) catalogue. */
    public const TRANSIENT = 'yaycheckout_compatibility_catalogue';

    /** Seconds: half a day for a remote result, an hour for the bundled fallback after a failed fetch. */
    public const TTL_REMOTE   = 43200;
    public const TTL_FALLBACK = 3600;

    /** The remote document's `version` this build understands. */
    public const CONTRACT_VERSION = 1;

    /**
     * @return array{gateways: array<string, array>, plugins: array<string, array>}
     */
    public static function bundled(): array {
        $core      = __( 'Core gateway.', 'yaycheckout' );
        $community = __( 'Reported working by merchants, not tested by us.', 'yaycheckout' );

        return [
            'gateways' => [
                'cod'          => self::entry( self::STATUS_VERIFIED, $core ),
                'bacs'         => self::entry( self::STATUS_VERIFIED, $core ),
                'cheque'       => self::entry( self::STATUS_VERIFIED, $core ),
                'stripe'       => self::entry( self::STATUS_COMMUNITY, $community ),
                'ppcp-gateway' => self::entry( self::STATUS_COMMUNITY, $community ),
            ],
            'plugins'  => [
                'yayboost'       => self::entry( self::STATUS_VERIFIED, __( 'Order bumps you can place as a block.', 'yaycheckout' ) ),
                'yayloyalty'     => self::entry( self::STATUS_VERIFIED, __( 'Points balance and redemption at checkout.', 'yaycheckout' ) ),
                'yaypricing-pro' => self::entry( self::STATUS_VERIFIED, __( 'Pricing rules, free gifts, cart discounts and fees show in the order summary.', 'yaycheckout' ) ),
            ],
        ];
    }

    /**
     * Reduces a decoded remote document to the shape bundled() has, or null
     * when the document is not one this build understands.
     *
     * Per entry: an unknown status drops the entry, non-string optional
     * fields become null, unknown keys and non-slug ids are dropped, and
     * docs_url must be an absolute http(s) URL because the SPA renders it as a link.
     *
     * @param  mixed $decoded json_decode() output.
     * @return array{gateways: array<string, array>, plugins: array<string, array>}|null
     */
    public static function normalize( $decoded ): ?array {
        if ( ! is_array( $decoded )
            || self::CONTRACT_VERSION !== ( $decoded['version'] ?? null )
            || ! is_array( $decoded['gateways'] ?? null )
            || ! is_array( $decoded['plugins'] ?? null ) ) {
            return null;
        }

        return [
            'gateways' => self::normalize_entries( $decoded['gateways'], self::GATEWAY_STATUSES ),
            'plugins'  => self::normalize_entries( $decoded['plugins'], self::PLUGIN_STATUSES ),
        ];
    }

    /**
     * @return array{title: null, status: null, express_pay: null, notes: null, docs_url: null}
     */
    public static function entry_defaults(): array {
        return [
            'title'       => null,
            'status'      => null,
            'express_pay' => null,
            'notes'       => null,
            'docs_url'    => null,
        ];
    }

    private static function entry( string $status, string $notes ): array {
        return array_merge(
            self::entry_defaults(),
            [
                'status' => $status,
                'notes'  => $notes,
            ]
        );
    }

    private static function normalize_entries( array $entries, array $statuses ): array {
        $normalized = [];

        foreach ( $entries as $id => $entry ) {
            if ( ! is_string( $id ) || ! preg_match( '/^[a-z0-9_-]+$/', $id ) || ! is_array( $entry ) ) {
                continue;
            }

            if ( ! in_array( $entry['status'] ?? null, $statuses, true ) ) {
                continue;
            }

            $normalized[ $id ] = [];
            foreach ( self::entry_defaults() as $key => $default ) {
                $value                     = $entry[ $key ] ?? $default;
                $normalized[ $id ][ $key ] = is_string( $value ) ? $value : $default;
            }

            if ( ! self::is_http_url( $normalized[ $id ]['docs_url'] ) ) {
                $normalized[ $id ]['docs_url'] = null;
            }
        }

        return $normalized;
    }

    private static function is_http_url( ?string $url ): bool {
        return is_string( $url )
            && preg_match( '#^https?://#i', $url )
            && false !== filter_var( $url, FILTER_VALIDATE_URL );
    }
}
