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
 * Layouts are created through core's wp/v2 endpoints rather than a controller
 * of ours, so there is no handler to put a count check in — hence a filter.
 * It has to live server-side: gating only the admin app would be bypassed by
 * anyone posting to wp/v2 directly, which any shop manager can do.
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
