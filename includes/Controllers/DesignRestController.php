<?php
namespace YayCheckout\Controllers;

use YayCheckout\Models\Design;
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

    /**
     * Body: { tokens?: object, preset_id?: string|null, custom_css?: string }.
     * Any key may be omitted to leave that part untouched; tokens are merged
     * over stored values so a partial object only changes the keys it names.
     */
    public function save_design( \WP_REST_Request $request ) {
        $input = $request->get_json_params();

        if ( ! is_array( $input ) ) {
            return $this->error_invalid_arguments();
        }

        $current = $this->design->get();
        $tokens  = $current->tokens;

        if ( array_key_exists( 'tokens', $input ) ) {
            if ( ! is_array( $input['tokens'] ) ) {
                return $this->error_invalid_arguments();
            }

            $tokens = Design::sanitize( array_replace_recursive( $current->tokens, $input['tokens'] ) );

            if ( is_wp_error( $tokens ) ) {
                return $this->error_invalid_arguments( $tokens->get_error_message() );
            }
        }

        $preset_id = $current->preset_id;

        if ( array_key_exists( 'preset_id', $input ) ) {
            $preset_id = $input['preset_id'];

            if ( null !== $preset_id && ! $current->has_preset( $preset_id ) ) {
                return $this->error_invalid_arguments( __( 'Unknown preset.', 'yaycheckout' ) );
            }
        }

        $custom_css = $current->custom_css;

        if ( array_key_exists( 'custom_css', $input ) ) {
            if ( ! is_string( $input['custom_css'] ) ) {
                return $this->error_invalid_arguments();
            }

            $custom_css = Design::sanitize_custom_css( $input['custom_css'] );

            if ( is_wp_error( $custom_css ) ) {
                return $this->error_invalid_arguments( $custom_css->get_error_message() );
            }
        }

        return $this->design->save( $tokens, $custom_css, $preset_id )->to_array();
    }
}
