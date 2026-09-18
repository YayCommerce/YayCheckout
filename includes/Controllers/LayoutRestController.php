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
 * Layout endpoints under /yaycheckout/v1/layouts. Draft saves stay on core's wp/v2 endpoints
 * (autosave, revisions); everything else the admin app needs lives here.
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
                    // exec_write_wp: a SQL rollback cannot undo what the object cache holds.
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'create_draft' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'name'          => [
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => static function ( $value ) {
                                return '' !== trim( (string) $value );
                            },
                        ],
                        'source'        => [
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                            'validate_callback' => static function ( $value ) {
                                return in_array( $value, [ 'blank', 'library' ], true );
                            },
                        ],
                        'template_slug' => [
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
                [
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'delete_layout' ], $request ),
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
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'publish_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/activate',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'activate_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/deactivate',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'deactivate_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );
        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/duplicate',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'duplicate_layout' ], $request ),
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
     * The collection: filtered, searched, paginated summaries.
     *
     * @return array|\WP_Error
     */
    public function get_layouts( \WP_REST_Request $request ) {
        try {
            $query = LayoutQuery::of_statuses( (array) $request->get_param( 'status' ) )
                ->with_search( (string) $request->get_param( 'search' ) )
                ->with_pagination( (int) $request->get_param( 'page' ) );
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }

        $result = $this->layouts->query( $query );

        return [
            'items'       => array_map(
                static function ( Layout $layout ) {
                    return $layout->to_summary_array();
                },
                $result['items']
            ),
            'total'       => $result['total'],
            'page'        => $query->page(),
            'total_pages' => $result['total_pages'],
        ];
    }

    /** Tab totals, independent of the status filter. Own route so the SPA can cache them independently of the filtered list. */
    public function get_layout_counts(): array {
        return [ 'counts' => $this->layouts->counts() ];
    }

    /**
     * @return array|\WP_Error
     */
    public function get_layout( \WP_REST_Request $request ) {
        $layout = $this->layouts->find( (int) $request->get_param( 'id' ) );

        if ( null === $layout ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        return $layout->to_array();
    }

    /** The "From library" template catalogue for the New layout modal. `plan` lets the SPA preview its build-time edition; omitted, the real plan decides. */
    public function get_templates( \WP_REST_Request $request ) {
        $plan_param = $request->get_param( 'plan' );
        $is_pro     = $plan_param ? ( 'pro' === $plan_param ) : Plan::is_pro();

        return [
            'templates' => LayoutTemplateCatalogue::templates( $is_pro ),
        ];
    }

    /**
     * Creates a layout draft from the New layout modal.
     * 'library' only checks the template exists; both sources create the same
     * blank draft until a real catalogue exists. Titles may repeat, as Pages do.
     *
     * @return array|\WP_Error
     */
    public function create_draft( \WP_REST_Request $request ) {
        $name          = (string) $request->get_param( 'name' );
        $source        = (string) $request->get_param( 'source' );
        $template_slug = (string) $request->get_param( 'template_slug' );

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
     * @return array|\WP_Error
     */
    public function publish_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        // Post-type check: without it any post id could be mutated by a manage_woocommerce user.
        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->publish( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            // Bad markup is the merchant's to fix: a 400 with the reason, not an opaque 500.
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Makes a layout the one shoppers see, publishing its draft first.
     *
     * @return array|\WP_Error
     */
    public function activate_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        // Post-type check, as in publish_layout().
        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->activate( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Stops serving a layout to shoppers. It stays published.
     *
     * @return array|\WP_Error
     */
    public function deactivate_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->deactivate( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Copies a layout's draft into a new draft layout.
     *
     * @return array|\WP_Error
     */
    public function duplicate_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->duplicate( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Permanently deletes an archived layout. Refusals (not archived, still active) surface as a 400.
     *
     * @return array|\WP_Error
     */
    public function delete_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            $this->layouts->delete( $id );
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }

        return [
            'id'      => $id,
            'deleted' => true,
        ];
    }

    /**
     * Collection query args. `status` has no `type => array`: WP validates type before sanitize, so a
     * comma-separated string would 400. LayoutQuery validates the vocabulary.
     */
    private function list_args(): array {
        return [
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
            'search' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'page'   => [
                'required'          => false,
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => static function ( $value ) {
                    return is_numeric( $value ) && (int) $value >= 1;
                },
            ],
        ];
    }

    private function id_args(): array {
        return [
            'id' => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                // Not bare 'is_numeric': REST passes three args and an internal function throws.
                'validate_callback' => static function ( $value ) {
                    return is_numeric( $value );
                },
            ],
        ];
    }
}
