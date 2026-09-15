<?php
namespace YayCheckout\Controllers;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutQuery;
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
                    'args'                => $this->list_args(),
                ],
                [

                    /*
                     * exec_write_wp, not exec_write: create_draft() writes a
                     * post through wp_insert_post(), and a SQL rollback cannot
                     * undo what the object cache already holds.
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
            '/layouts/counts',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_layout_counts' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
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
     * With `case`, returns dump data for the Layouts screen review dropdown.
     * Without it, returns the real collection: filtered summaries.
     *
     * @param  \WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function get_layouts( \WP_REST_Request $request ) {
        $case_param = $request->get_param( 'case' );

        if ( $case_param ) {
            $case_key = (string) $case_param;

            return array_merge( [ 'case' => $case_key ], LayoutsDumpData::get( $case_key ) );
        }

        try {
            $query = LayoutQuery::of_statuses( (array) $request->get_param( 'status' ) );
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }

        $layouts = $this->layouts->query( $query );

        return [
            'items' => array_map(
                static function ( Layout $layout ) {
                    return $layout->to_summary_array();
                },
                $layouts
            ),
        ];
    }

    /**
     * Tab totals, independent of the current status filter. Its own route so
     * the counts query (see LayoutsPage.tsx) doesn't share a cache key or
     * response payload with the filtered list query.
     *
     * @return array
     */
    public function get_layout_counts(): array {
        return [ 'counts' => $this->layouts->counts() ];
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
     * Returns the "From library" template catalogue for the New layout modal
     * (YC-02b_New_Layout_Modal.dc.html). See LayoutTemplateCatalogue for why
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
            'templates' => LayoutTemplateCatalogue::templates( $is_pro ),
        ];
    }

    /**
     * Creates a layout draft from the New layout modal.
     *
     * 'blank' and 'library' both create the same blank draft — only the
     * template's existence is checked for 'library' — until a real catalogue
     * exists to source markup from.
     *
     * No duplicate-title check: titles may repeat, same as WordPress Pages.
     * publish() dedupes the post_name slug on its own once first published.
     *
     * @param  \WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function create_draft( \WP_REST_Request $request ) {
        $name          = (string) $request->get_param( 'name' );
        $source        = (string) $request->get_param( 'source' );
        $template_slug = (string) $request->get_param( 'templateSlug' );

        if ( 'library' === $source && ! LayoutTemplateCatalogue::has_template( $template_slug ) ) {
            return $this->error_invalid_arguments( __( 'Unknown template.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->create( $name )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
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
     * LayoutQuery checks the LayoutStatus vocabulary.
     *
     * `case` is the dump-data switch used by LayoutsPage while the screen is
     * reviewed against YC-02. Real callers omit it.
     *
     * @return array
     */
    private function list_args(): array {
        return [
            'case'   => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
            ],
            'status' => [
                'default'           => LayoutStatus::listed(),
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
