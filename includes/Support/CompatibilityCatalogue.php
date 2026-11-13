<?php
namespace YayCheckout\Support;

defined( 'ABSPATH' ) || exit;

/**
 * What YayCheckout knows about gateways and plugins at checkout: the status
 * vocabulary and the bundled list every install ships with.
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

    /** Transient an earlier build cached the catalogue in; uninstall still clears it. */
    public const TRANSIENT = 'yaycheckout_compatibility_catalogue';

    /**
     * @return array{gateways: array<string, array>, plugins: array<string, array>}
     */
    public static function bundled(): array {
        $core      = __( 'Core gateway.', 'yaycheckout' );
        $community = __( 'Reported working by merchants, not tested by us.', 'yaycheckout' );

        return [
            'gateways' => [
                'cod'             => self::entry( self::STATUS_VERIFIED, $core ),
                'bacs'            => self::entry( self::STATUS_VERIFIED, $core ),
                'cheque'          => self::entry( self::STATUS_VERIFIED, $core ),
                'stripe'          => self::entry( self::STATUS_COMMUNITY, $community ),
                'ppcp-gateway'    => self::entry( self::STATUS_COMMUNITY, $community ),
                'klarna_payments' => self::entry( self::STATUS_VERIFIED, __( 'Pay later and pay in parts.', 'yaycheckout' ) ),
            ],
            'plugins'  => [
                'yayboost'                        => self::entry( self::STATUS_VERIFIED, __( 'Order bumps you can place as a block.', 'yaycheckout' ) ),
                'yayloyalty'                      => self::entry( self::STATUS_VERIFIED, __( 'Points balance and redemption at checkout.', 'yaycheckout' ) ),
                'klarna-payments-for-woocommerce' => self::entry( self::STATUS_VERIFIED, __( 'Klarna payments and Sign in with Klarna at checkout.', 'yaycheckout' ) ),
            ],
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
}
