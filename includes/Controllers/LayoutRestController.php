<?php
namespace YayCheckout\Controllers;

use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Support\Plan;
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
                    'args'                => [
                        'case' => [
                            'required'          => false,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
                [

                    /*
                     * exec_write_wp, not exec_write: mirrors publish_layout —
                     * no SQL transaction is needed because create_draft() does
                     * not touch the database yet (see its docblock).
                     */
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'create_draft' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'name'         => [
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => static function ( $value ) {
                                return '' !== trim( (string) $value );
                            },
                        ],
                        'source'       => [
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                            'validate_callback' => static function ( $value ) {
                                return in_array( $value, [ 'blank', 'library' ], true );
                            },
                        ],
                        'templateSlug' => [
                            'required'          => false,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                            'validate_callback' => static function ( $value, $request ) {
                                if ( 'library' !== $request->get_param( 'source' ) ) {
                                    return true;
                                }

                                return is_string( $value ) && '' !== trim( $value );
                            },
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/templates',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_templates' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'plan' => [
                            'required'          => false,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                            'validate_callback' => static function ( $value ) {
                                return in_array( $value, [ 'free', 'pro' ], true );
                            },
                        ],
                    ],
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
                    'args'                => [
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
                    ],
                ],
            ]
        );
    }

    public function permission_callback(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Returns dump data for one Layouts-screen case, keyed by the `case` query
     * param. Temporary: bypasses LayoutRepository entirely so the screen can be
     * built and reviewed against every state in YC-02_Layouts_Home.dc.html
     * before the real backend (health, compatibility, archive, safe mode,
     * assignment rules) exists. Repoint this at $this->layouts->all() once it
     * does, and delete LayoutsDumpData.
     *
     * @param  \WP_REST_Request $request
     * @return array
     */
    public function get_layouts( \WP_REST_Request $request ) {
        $case_param = $request->get_param( 'case' );
        $case_key   = $case_param ? (string) $case_param : LayoutsDumpData::DEFAULT_CASE;

        return array_merge( [ 'case' => $case_key ], LayoutsDumpData::get( $case_key ) );
    }

    /**
     * Returns the "From library" template catalogue for the New layout modal
     * (YC-02b_New_Layout_Modal.dc.html). See LayoutTemplatesDumpData for why
     * this is fixture data rather than a real template library.
     *
     * The `plan` param lets the SPA preview against its own build-time edition
     * (apps/admin/src/lib/plan.ts) rather than this site's actual licence, so
     * the locked/unlocked grid can be reviewed without rebuilding. Omit it and
     * the real Plan::is_pro() decides, for any caller outside the SPA.
     *
     * @param  \WP_REST_Request $request
     * @return array
     */
    public function get_templates( \WP_REST_Request $request ) {
        $plan_param = $request->get_param( 'plan' );
        $is_pro     = $plan_param ? ( 'pro' === $plan_param ) : Plan::is_pro();

        return [
            'templates' => LayoutTemplatesDumpData::templates( $is_pro ),
        ];
    }

    /**
     * Creates a layout draft from the New layout modal. Validates the name
     * against LayoutTemplatesDumpData's fixed collision list and, when it is
     * free, returns a fake success payload — it never touches the database.
     * Repoint at LayoutRepository::create() once that exists.
     *
     * @param  \WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function create_draft( \WP_REST_Request $request ) {
        $name = (string) $request->get_param( 'name' );

        $suggested_name = LayoutTemplatesDumpData::check_duplicate_name( $name );

        if ( null !== $suggested_name ) {
            return new \WP_Error(
                'duplicate_name',
                sprintf(
                    /* translators: %s: the layout name the merchant typed. */
                    __( 'A layout called "%s" already exists.', 'yaycheckout' ),
                    $name
                ),
                [
                    'status'         => 409,
                    'suggestedName' => $suggested_name,
                ]
            );
        }

        return [
            'id'           => wp_rand( 1000, 9999 ),
            'name'         => $name,
            'source'       => (string) $request->get_param( 'source' ),
            'templateSlug' => $request->get_param( 'templateSlug' ),
        ];
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
}
