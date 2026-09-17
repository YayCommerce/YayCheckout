<?php
namespace YayCheckout\Engine;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Support\Plan;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces the edition's layout limit at the point layouts are created.
 *
 * The admin app creates through POST /layouts, where LayoutRepository::create()
 * checks the limit; but the post type is on wp/v2 and any shop manager can
 * POST there directly, so the same rule is enforced here as a filter on core's
 * insert. Gating only the admin app would be bypassed that way.
 */
class LayoutCapGuard {

    use SingletonTrait;

    protected function __construct() {
        add_filter( 'rest_pre_insert_' . Layout::POST_TYPE, [ $this, 'enforce_limit' ], 10, 2 );
    }

    /**
     * @param  \stdClass        $prepared_post
     * @param  \WP_REST_Request $request
     * @return \stdClass|\WP_Error
     */
    public function enforce_limit( $prepared_post, $request ) {
        unset( $request );

        /*
         * This filter fires for updates as well as inserts. An update carries
         * the existing post ID, and editing a layout must keep working once the
         * limit is reached — only creating another one consumes a slot.
         */
        if ( ! empty( $prepared_post->ID ) ) {
            return $prepared_post;
        }

        $repository = new LayoutRepository();

        if ( ! Plan::layout_limit_reached( $repository->count() ) ) {
            return $prepared_post;
        }

        return new \WP_Error(
            'yaycheckout_layout_limit_reached',
            Plan::layout_limit_message(),
            [ 'status' => 403 ]
        );
    }
}
