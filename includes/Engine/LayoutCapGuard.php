<?php
namespace YayCheckout\Engine;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Support\Plan;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces the edition's layout limit where layouts are created.
 * The post type is on wp/v2, so the cap must also gate core's insert
 * or a direct POST bypasses it.
 */
class LayoutCapGuard {

    use SingletonTrait;

    protected function __construct() {
        add_filter( 'rest_pre_insert_' . Layout::POST_TYPE, [ $this, 'enforce_limit' ], 10, 2 );
    }

    /** @return \stdClass|\WP_Error */
    public function enforce_limit( $prepared_post, $request ) {
        unset( $request );

        // Fires for updates too; only an insert consumes a slot.
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
