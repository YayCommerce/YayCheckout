<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Helpers\ExceptionHelper;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sets the order the storefront tries the active layouts in. Pro-only: Free serves its one
 * active layout, so the Free build has no route.
 */
class LayoutPriorityRestController extends BaseRestController {

    use SingletonTrait;

    private LayoutRepository $layouts;

    protected function __construct() {
        $this->layouts = new LayoutRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/priority',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_priority' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'save_priority' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'ids' => [
                            'required' => true,
                            'type'     => 'array',
                            'items'    => [
                                'type'    => 'integer',
                                'minimum' => 1,
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    /** @return array { layouts: summary[] }, top priority first. */
    public function get_priority(): array {
        return [ 'layouts' => array_map( static fn( Layout $layout ) => $layout->to_summary_array(), $this->layouts->priority() ) ];
    }

    /**
     * Body: { ids: int[] }, top priority first. Replies as GET does. 409 when the list is not the active set.
     *
     * @return array|\WP_Error { layouts }
     */
    public function save_priority( \WP_REST_Request $request ) {
        $ids = array_map( 'intval', (array) $request->get_param( 'ids' ) );

        try {
            $this->layouts->set_priority( $ids );
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_conflict( ExceptionHelper::message( $ex ) );
        }

        return $this->get_priority();
    }
}
