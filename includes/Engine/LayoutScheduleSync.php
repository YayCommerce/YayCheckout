<?php
namespace YayCheckout\Engine;

use YayCheckout\Helpers\Logger;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Core's cron only flips post_status; the snapshot must be written here or a
 * scheduled layout reaches its date with nothing to serve.
 */
class LayoutScheduleSync {

    use SingletonTrait;

    protected function __construct() {
        add_action( 'future_to_publish', [ $this, 'snapshot_published_layout' ] );
    }

    public function snapshot_published_layout( $post ): void {
        // publish_future_post fires once per real transition: no double snapshot, no re-entry from publish().
        if (
            ! doing_action( 'publish_future_post' )
            || ! $post instanceof \WP_Post
            || Layout::POST_TYPE !== $post->post_type
        ) {
            return;
        }

        try {
            ( new LayoutRepository() )->publish( (int) $post->ID );
        } catch ( \InvalidArgumentException $ex ) {
            // Previous snapshot keeps serving, but post_status already reads 'publish'.
            Logger::exception( $ex, 'publish_future_post', [ 'layout_id' => (int) $post->ID ] );
        }
    }
}
