<?php
namespace YayCheckout\Helpers;

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use YayCheckout\Support\CompatibilityCatalogue;

defined( 'ABSPATH' ) || exit;

/**
 * The gateway rows on the Compatibility page: every enabled classic gateway
 * with its catalogue status, then every active Checkout-block payment method
 * that has no classic gateway behind it, flagged block-only — the one a
 * shopper would find missing from a YayCheckout checkout.
 */
class GatewayCompatibilityHelper {

    /**
     * @param  array $catalogue CompatibilityCatalogueRepository::get() output; only 'gateways' is read.
     * @return array<int, array{id: string, title: string, status: string, express_pay: ?string, notes: ?string, docs_url: ?string}>
     */
    public static function list( array $catalogue ): array {
        $entries = $catalogue['gateways'] ?? [];
        $classic = WC()->payment_gateways()->payment_gateways();

        $rows = [];
        foreach ( $classic as $gateway ) {
            if ( 'yes' === $gateway->enabled ) {
                $rows[] = self::row( $gateway->id, $gateway->get_method_title(), $entries[ $gateway->id ] ?? [], CompatibilityCatalogue::STATUS_UNVERIFIED );
            }
        }

        // Diff against every classic gateway, enabled or not: a block integration's is_active() defaults to true, so a switched-off gateway must not surface as block-only.
        foreach ( self::block_registered_ids() as $id ) {
            if ( isset( $classic[ $id ] ) ) {
                continue;
            }

            $entry  = $entries[ $id ] ?? [];
            $rows[] = self::row( $id, $entry['title'] ?? self::humanize( $id ), $entry, CompatibilityCatalogue::STATUS_BLOCK_ONLY );
        }

        return $rows;
    }

    /**
     * @return string[]
     */
    private static function block_registered_ids(): array {
        if ( ! class_exists( Package::class ) || ! class_exists( PaymentMethodRegistry::class ) ) {
            return [];
        }

        // The container throws when the Blocks bootstrap never ran; without the registry there are simply no block-only rows.
        try {
            $methods = Package::container()->get( PaymentMethodRegistry::class )->get_all_registered();
        } catch ( \Throwable $ex ) {
            return [];
        }

        $ids = [];
        foreach ( $methods as $name => $method ) {
            // A third-party integration's is_active() may assume a storefront request; one broken gateway must not break the page.
            try {
                if ( $method->is_active() ) {
                    $ids[] = (string) $name;
                }
            } catch ( \Throwable $ex ) {
                continue;
            }
        }

        return $ids;
    }

    private static function row( string $id, string $title, array $entry, string $detected_status ): array {
        return [
            'id'          => $id,
            'title'       => $title,
            'status'      => $entry['status'] ?? $detected_status,
            'express_pay' => $entry['express_pay'] ?? null,
            'notes'       => $entry['notes'] ?? null,
            'docs_url'    => $entry['docs_url'] ?? null,
        ];
    }

    private static function humanize( string $id ): string {
        return ucwords( str_replace( [ '_', '-' ], ' ', $id ) );
    }
}
