<?php
namespace YayCheckout\Repositories;

use YayCheckout\Support\CompatibilityCatalogue;

defined( 'ABSPATH' ) || exit;

/**
 * The compatibility catalogue this install runs on: the list bundled with the plugin.
 */
class CompatibilityCatalogueRepository {

    /**
     * The bundled list in the shape the Compatibility report returns.
     *
     * @return array{gateways: array, plugins: array, source: string, fetched_at: null}
     */
    public static function bundled(): array {
        return CompatibilityCatalogue::bundled() + [
            'source'     => CompatibilityCatalogue::SOURCE_BUNDLED,
            'fetched_at' => null,
        ];
    }
}
