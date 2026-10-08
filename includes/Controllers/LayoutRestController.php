<?php
namespace YayCheckout\Controllers;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Helpers\DateHelper;
use YayCheckout\Helpers\PreviewToken;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutQuery;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
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
            '/layouts/(?P<id>\d+)/archive',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'archive_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/restore',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'restore_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/preview-link',
            [
                [
                    // POST because a link is minted with a fresh expiry; nothing is stored.
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'create_preview_link' ], $request ),
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

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/reset',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'reset_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );
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

    /** Tab totals, independent of the status filter, on their own route so the SPA caches them apart from the list. */
    public function get_layout_counts(): array {
        return [ 'counts' => $this->layouts->counts() ];
    }

    /** @return array|\WP_Error */
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
     * Creates a layout draft from the New layout modal. Titles may repeat, as Pages do.
     * 'library' only checks the template is available on this plan: both sources seed the same default draft.
     *
     * @return array|\WP_Error
     */
    public function create_draft( \WP_REST_Request $request ) {
        $name          = (string) $request->get_param( 'name' );
        $source        = (string) $request->get_param( 'source' );
        $template_slug = (string) $request->get_param( 'template_slug' );

        try {
            if ( 'library' === $source ) {
                LayoutTemplateCatalogue::assert_available( $template_slug );
            }

            return $this->layouts->create( $name )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /** @return array|\WP_Error */
    public function publish_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        // Post-type check: without it any post id could be mutated by a manage_woocommerce user.
        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->publish( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Makes a layout the one shoppers see, publishing it first.
     *
     * @return array|\WP_Error
     */
    public function activate_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

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
     * Moves a layout to the archive, deactivating it if it was active.
     *
     * @return array|\WP_Error
     */
    public function archive_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->archive( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Moves an archived layout back to draft.
     *
     * @return array|\WP_Error
     */
    public function restore_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->restore( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Copies a layout's content into a new draft layout.
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
     * Replaces a layout's blocks with the default layout (LayoutRepository::default_markup()).
     *
     * @return array|\WP_Error
     */
    public function reset_layout( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        try {
            return $this->layouts->reset( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Mints an expiring preview URL for any layout, archived included. The lifetime
     * is read now, so changing the setting later leaves issued links as they were.
     *
     * @return array|\WP_Error
     */
    public function create_preview_link( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        $lifetime = (int) ( new SettingsRepository() )->get()->advanced['preview_link_lifetime'];
        $minted   = PreviewToken::mint( $id, $lifetime );

        return [
            'url'        => PreviewToken::checkout_url( $minted['token'] ),
            'expires_at' => DateHelper::to_iso8601( gmdate( 'Y-m-d H:i:s', $minted['expires_at'] ) ),
        ];
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
}
