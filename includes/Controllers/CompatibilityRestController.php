<?php
namespace YayCheckout\Controllers;

use YayCheckout\Helpers\CheckoutHookScanHelper;
use YayCheckout\Helpers\GatewayCompatibilityHelper;
use YayCheckout\Helpers\Logger;
use YayCheckout\Helpers\StoreEnvironmentHelper;
use YayCheckout\Repositories\CompatibilityCatalogueRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * GET /yaycheckout/v1/compatibility?refresh= — the Compatibility page in one
 * read: { store, gateways, plugins, catalogue: { source, fetched_at } }.
 * Each detection fails on its own to null, which the SPA shows as "Not
 * detected", so one broken check never costs the merchant the whole report.
 */
class CompatibilityRestController extends BaseRestController {
    use SingletonTrait;

    protected function __construct() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/compatibility',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_report' ], $request ),
                'permission_callback' => [ $this, 'permission_callback' ],
                'args'                => [
                    'refresh' => [
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                ],
            ]
        );
    }

    public function get_report( \WP_REST_Request $request ): array {
        $catalogue = $this->detect( 'catalogue', fn() => ( new CompatibilityCatalogueRepository() )->get( (bool) $request['refresh'] ) )
            ?? CompatibilityCatalogueRepository::bundled();

        return [
            'store'     => $this->detect( 'store', fn() => StoreEnvironmentHelper::describe() ),
            'gateways'  => $this->detect( 'gateways', fn() => GatewayCompatibilityHelper::list( $catalogue ) ),
            'plugins'   => $this->detect( 'plugins', fn() => ( new CheckoutHookScanHelper() )->scan( $catalogue ) ),
            'catalogue' => [
                'source'     => $catalogue['source'],
                'fetched_at' => $catalogue['fetched_at'],
            ],
        ];
    }

    /**
     * Runs one detection, or logs why it threw and returns null.
     *
     * @return array|null
     */
    private function detect( string $name, callable $detection ) {
        try {
            return $detection();
        } catch ( \Throwable $ex ) {
            Logger::exception( $ex, 'compatibility scan: ' . $name );

            return null;
        }
    }
}
