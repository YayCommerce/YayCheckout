<?php
namespace YayCheckout\Controllers;

use YayCheckout\Repositories\DesignRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /yaycheckout/v1/design. Both return { tokens, preset_id, custom_css, updated_at, presets }.
 */
class DesignRestController extends BaseRestController {
    use SingletonTrait;

    private DesignRepository $design;

    protected function __construct() {
        $this->design = new DesignRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/design',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_design' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'save_design' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
    }

    public function permission_callback(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    public function get_design( \WP_REST_Request $request ) {
        return $this->design->get()->to_array();
    }

    /** Body: { tokens, preset_id, custom_css } — the full design, every key required. */
    public function save_design( \WP_REST_Request $request ) {
        $input = $request->get_json_params();

        if ( ! is_array( $input ) ) {
            return $this->error_invalid_arguments();
        }

        try {
            return $this->design->save( $input )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }
}
