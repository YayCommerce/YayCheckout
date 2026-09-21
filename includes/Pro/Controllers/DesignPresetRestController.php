<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Repositories\DesignRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * POST /yaycheckout/v1/design/presets and DELETE /design/presets/{id}.
 * Both return the full design envelope, the same shape as GET /design,
 * so the SPA replaces its cache in one step.
 */
class DesignPresetRestController extends BaseRestController {
    use SingletonTrait;

    private DesignRepository $design;

    protected function __construct() {
        $this->design = new DesignRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/design/presets',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'add_preset' ], $request ),
                'permission_callback' => [ $this, 'permission_callback' ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/design/presets/(?P<id>[A-Za-z0-9-]+)',
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'delete_preset' ], $request ),
                'permission_callback' => [ $this, 'permission_callback' ],
            ]
        );
    }

    public function permission_callback(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    /** Body: { name, tokens }. */
    public function add_preset( \WP_REST_Request $request ) {
        $input = $request->get_json_params();

        if ( ! is_array( $input ) ) {
            return $this->error_invalid_arguments();
        }

        $name   = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
        $design = $this->design->add_preset( $name, $input['tokens'] ?? null );

        if ( is_wp_error( $design ) ) {
            return $this->error_invalid_arguments( $design->get_error_message() );
        }

        return $design->to_array();
    }

    public function delete_preset( \WP_REST_Request $request ) {
        $design = $this->design->delete_preset( $request->get_param( 'id' ) );

        if ( is_wp_error( $design ) ) {
            return $this->error_not_found( $design->get_error_message() );
        }

        return $design->to_array();
    }
}
