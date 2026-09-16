<?php
namespace YayCheckout\Controllers;

use YayCheckout\Models\Settings;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Settings REST endpoints: GET/POST /wp-json/yaycheckout/v1/settings
 *
 * Both return the envelope { settings, updated_at }. POST takes the settings
 * object (snake_case, typed JSON); unknown keys are dropped and a wrong type
 * or enum value is a 400. See Settings::sanitize() for the rules.
 */
class SettingsRestController extends BaseRestController {
    use SingletonTrait;

    private SettingsRepository $settings;

    protected function __construct() {
        $this->settings = new SettingsRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/settings',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_settings' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'save_settings' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/mark-reviewed',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'mark_reviewed' ], $request ),
                'permission_callback' => [ $this, 'permission_callback' ],
            ]
        );
    }

    public function permission_callback(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    public function get_settings( \WP_REST_Request $request ) {
        return $this->settings->get()->to_array();
    }

    public function save_settings( \WP_REST_Request $request ) {
        $input = $request->get_json_params();

        if ( ! is_array( $input ) ) {
            return $this->error_invalid_arguments();
        }

        // Merge over the stored values first so a partial body updates only
        // the keys it names instead of resetting the rest to defaults.
        $clean = Settings::sanitize( array_replace_recursive( $this->settings->get()->values(), $input ) );

        if ( is_wp_error( $clean ) ) {
            return $this->error_invalid_arguments( $clean->get_error_message() );
        }

        return $this->settings->save( $clean )->to_array();
    }

    public function mark_reviewed( \WP_REST_Request $request ) {
        $this->settings->mark_reviewed();
        return true;
    }
}
