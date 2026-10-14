<?php
namespace YayCheckout\Controllers;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Helpers\DateHelper;
use YayCheckout\Helpers\PreviewToken;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutQuery;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
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
                        'name'          => $this->name_arg(),
                        'source'        => [
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                            'validate_callback' => static function ( $value ) {
                                return in_array( $value, [ 'default', 'library' ], true );
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
            '/layouts/(?P<id>\d+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'rename_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => array_merge( $this->id_args(), [ 'name' => $this->name_arg() ] ),
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
            return $this->error_layout_not_found();
        }

        return $layout->to_array();
    }

    /**
     * Creates a layout draft from the New layout modal. Titles may repeat, as Pages do.
     * 'default' builds from the default template, 'library' from the picked one, once it is available on this plan.
     *
     * @return array|\WP_Error
     */
    public function create_draft( \WP_REST_Request $request ) {
        $name          = (string) $request->get_param( 'name' );
        $source        = (string) $request->get_param( 'source' );
        $template_slug = 'library' === $source ? (string) $request->get_param( 'template_slug' ) : LayoutTemplateCatalogue::DEFAULT_SLUG;

        try {
            LayoutTemplateCatalogue::assert_available( $template_slug );

            return $this->layouts->create( $name, $template_slug )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /** @return array|\WP_Error */
    public function publish_layout( \WP_REST_Request $request ) {
        return $this->change_layout( $request, fn( int $id ) => $this->layouts->publish( $id ) );
    }

    /**
     * Makes a layout the one shoppers see, publishing it first; on Free, drafts the one it replaces.
     *
     * @return array|\WP_Error
     */
    public function activate_layout( \WP_REST_Request $request ) {
        return $this->change_layout( $request, fn( int $id ) => $this->layouts->activate( $id ) );
    }

    /**
     * Stops serving a layout to shoppers and returns it to draft.
     *
     * @return array|\WP_Error
     */
    public function deactivate_layout( \WP_REST_Request $request ) {
        return $this->change_layout( $request, fn( int $id ) => $this->layouts->deactivate( $id ) );
    }

    /**
     * Moves a layout to the archive, deactivating it if it was active.
     *
     * @return array|\WP_Error
     */
    public function archive_layout( \WP_REST_Request $request ) {
        return $this->change_layout( $request, fn( int $id ) => $this->layouts->archive( $id ) );
    }

    /**
     * Moves an archived layout back to draft.
     *
     * @return array|\WP_Error
     */
    public function restore_layout( \WP_REST_Request $request ) {
        return $this->change_layout( $request, fn( int $id ) => $this->layouts->restore( $id ) );
    }

    /**
     * Copies a layout's content into a new draft layout.
     *
     * @return array|\WP_Error
     */
    public function duplicate_layout( \WP_REST_Request $request ) {
        return $this->change_layout( $request, fn( int $id ) => $this->layouts->duplicate( $id ) );
    }

    /**
     * Changes a layout's title from the layouts list's Rename action.
     *
     * @return array|\WP_Error
     */
    public function rename_layout( \WP_REST_Request $request ) {
        return $this->change_layout( $request, fn( int $id ) => $this->layouts->rename( $id, (string) $request->get_param( 'name' ) ) );
    }

    /**
     * Replaces a layout's blocks and design with its template's (LayoutRepository::reset()).
     *
     * @return array|\WP_Error
     */
    public function reset_layout( \WP_REST_Request $request ) {
        return $this->change_layout( $request, fn( int $id ) => $this->layouts->reset( $id ) );
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
            return $this->error_layout_not_found();
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
            return $this->error_layout_not_found();
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
     * Runs $op on the requested layout and replies with the layout it returns: 404 when the id
     * is not a layout, 400 when $op refuses. The post-type check is what stops a
     * manage_woocommerce user mutating any post id.
     *
     * @param callable $op fn( int $id ): Layout.
     * @return array|\WP_Error
     */
    private function change_layout( \WP_REST_Request $request, callable $op ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_layout_not_found();
        }

        try {
            return $op( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /** A layout's name, as create and rename take it. Titles may repeat, as Pages do. */
    private function name_arg(): array {
        return [
            'required'          => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => static function ( $value ) {
                return '' !== trim( (string) $value );
            },
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
