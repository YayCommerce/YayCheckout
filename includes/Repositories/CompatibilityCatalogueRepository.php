<?php
namespace YayCheckout\Repositories;

use YayCheckout\Helpers\DateHelper;
use YayCheckout\Support\CompatibilityCatalogue;

defined( 'ABSPATH' ) || exit;

/**
 * The compatibility catalogue this install runs on: the remote list when it
 * can be fetched, the bundled one otherwise, cached in a transient either way.
 */
class CompatibilityCatalogueRepository {

    /**
     * @param  bool $refresh Drop the cached catalogue and fetch again.
     * @return array{gateways: array, plugins: array, source: string, fetched_at: ?string}
     */
    public function get( bool $refresh = false ): array {
        if ( $refresh ) {
            delete_transient( CompatibilityCatalogue::TRANSIENT );
        }

        $cached = get_transient( CompatibilityCatalogue::TRANSIENT );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $remote = $this->fetch_remote();

        // A failed fetch caches the bundled list too, so a dead endpoint costs one attempt per TTL_FALLBACK.
        $catalogue = $remote
            ? $remote + [
                'source'     => CompatibilityCatalogue::SOURCE_REMOTE,
                'fetched_at' => DateHelper::now_iso8601(),
            ]
            : CompatibilityCatalogue::bundled() + [
                'source'     => CompatibilityCatalogue::SOURCE_BUNDLED,
                'fetched_at' => null,
            ];

        set_transient(
            CompatibilityCatalogue::TRANSIENT,
            $catalogue,
            $remote ? CompatibilityCatalogue::TTL_REMOTE : CompatibilityCatalogue::TTL_FALLBACK
        );

        return $catalogue;
    }

    private function fetch_remote(): ?array {
        $response = wp_remote_get( YAYCHECKOUT_COMPATIBILITY_CATALOGUE_URL, [ 'timeout' => 5 ] );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        return CompatibilityCatalogue::normalize( json_decode( wp_remote_retrieve_body( $response ), true ) );
    }
}
