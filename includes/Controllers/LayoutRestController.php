<?php
namespace YayCheckout\Controllers;

use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutQuery;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Layout endpoints: GET/POST /wp-json/yaycheckout/v1/layouts...
 *
 * Only the operations core cannot express live here. The block editor saves a
 * layout's draft through core's own wp/v2/yay_checkout_layout endpoints, which
 * bring autosave and revisions with them; publishing is a sequence rather than
 * a field, so it is ours. Both surfaces check manage_woocommerce — core through
 * the post type's capability map, this controller through permission_callback.
 */
class LayoutRestController extends BaseRestController {

    use SingletonTrait;

    private LayoutRepository $layouts;

    protected function __construct() {
        $this->layouts = new LayoutRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_layouts' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->list_args(),
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/publish',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,

                    /*
                     * exec_write_wp, not exec_write: publishing writes posts and
                     * meta through the WordPress API, and a SQL rollback cannot
                     * undo what the object cache already holds.
                     */
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'publish_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );
    }

    public function permission_callback(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * @param  \WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function get_layouts( \WP_REST_Request $request ) {
        try {
            $query = LayoutQuery::of_statuses( (array) $request->get_param( 'status' ) );
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }

        $layouts = $this->layouts->query( $query );

        return [
            'items'  => array_map(
                static function ( Layout $layout ) {
                    return $layout->to_summary_array();
                },
                $layouts
            ),
            'counts' => $this->layouts->counts(),
        ];
    }

    /**
     * @param  \WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function get_layout( \WP_REST_Request $request ) {
        $layout = $this->layouts->find( (int) $request->get_param( 'id' ) );

        if ( null === $layout ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        return $layout->to_array();
    }

    /**
     * @param  \WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function publish_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        /*
         * find() returns null for any post that is not a layout, so an id
         * belonging to a page or an order cannot be published. Without this the
         * route would be an arbitrary-post mutation endpoint for anyone holding
         * manage_woocommerce.
         */
        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->publish( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            /*
             * Unusable layout markup is the merchant's problem to fix, not a
             * plugin fault. Report it as a 400 with the reason rather than
             * letting exec_write_wp turn it into an opaque 500.
             */
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Collection query args.
     *
     * `status` has no `type => array`: WP validates type before sanitize, so
     * `?status=draft,published` (a string) would 400. Accept string or array;
     * LayoutQuery checks the lifecycle vocabulary.
     *
     * @return array
     */
    private function list_args(): array {
        return [
            'status' => [
                'default'           => [ Layout::LIFECYCLE_DRAFT, Layout::LIFECYCLE_PUBLISHED ],
                'validate_callback' => static function ( $value ) {
                    return is_string( $value ) || is_array( $value );
                },
                'sanitize_callback' => static function ( $value ) {
                    if ( is_string( $value ) ) {
                        $value = explode( ',', $value );
                    }

                    return array_values( array_filter( array_map( 'sanitize_key', (array) $value ) ) );
                },
            ],
        ];
    }

    /**
     * @return array
     */
    private function id_args(): array {
        return [
            'id' => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',

                /*
                 * Wrapped rather than passing 'is_numeric' directly:
                 * REST calls validate callbacks with three arguments
                 * ( $value, $request, $param ), and an internal
                 * function that takes one raises ArgumentCountError.
                 */
                'validate_callback' => static function ( $value ) {
                    return is_numeric( $value );
                },
            ],
        ];
    }
}
