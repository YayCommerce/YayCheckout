<?php
namespace YayCheckout\Controllers;

use YayCheckout\Helpers\CheckoutHookScanHelper;
use YayCheckout\Helpers\GatewayCompatibilityHelper;
use YayCheckout\Helpers\StoreEnvironmentHelper;
use YayCheckout\Renderer\CheckoutHealth;
use YayCheckout\Repositories\CompatibilityCatalogueRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * GET /yaycheckout/v1/compatibility?refresh= — the Compatibility page in one
 * read: { store, gateways, plugins, catalogue: { source, fetched_at } }.
 * GET /yaycheckout/v1/compatibility/problems — only { safe_mode, checkout_problems },
 * for the Layouts page: no hook scan, no gateway list, never fetches the catalogue.
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

        register_rest_route(
            self::REST_NAMESPACE,
            '/compatibility/problems',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_problems' ], $request ),
                'permission_callback' => [ $this, 'permission_callback' ],
            ]
        );
    }

    /** @return array{safe_mode: bool, checkout_problems: array} */
    public function get_problems(): array {
        return [
            'safe_mode'         => ( new SettingsRepository() )->is_safe_mode(),
            'checkout_problems' => CheckoutHealth::reported_problems(),
        ];
    }

    public function get_report( \WP_REST_Request $request ): array {
        $catalogue = ( new CompatibilityCatalogueRepository() )->get( (bool) $request['refresh'] );

        return [
            'store'     => StoreEnvironmentHelper::describe(),
            'gateways'  => GatewayCompatibilityHelper::list( $catalogue ),
            'plugins'   => ( new CheckoutHookScanHelper() )->scan( $catalogue ),
            'catalogue' => [
                'source'     => $catalogue['source'],
                'fetched_at' => $catalogue['fetched_at'],
            ],
        ];
    }
}
