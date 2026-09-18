<?php
namespace YayCheckout\Engine;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Helpers\Logger;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the snapshot whenever a layout becomes published, whatever moved it.
 * Core paths (the editor's Publish button, cron, quick edit, CLI) only flip
 * post_status; hooking the transition is what lets 'publish' mean "has a
 * snapshot". Unusable markup can still arrive from non-REST paths; the row
 * then reads 'publish' while the previous snapshot keeps serving.
 */
class LayoutPublishSync {

    use SingletonTrait;

    protected function __construct() {
        add_action( 'transition_post_status', [ $this, 'snapshot_on_publish' ], 10, 3 );
    }

    public function snapshot_on_publish( $new_status, $old_status, $post ): void {
        if (
            LayoutStatus::PUBLISHED !== $new_status
            || LayoutStatus::PUBLISHED === $old_status
            || ! $post instanceof \WP_Post
            || Layout::POST_TYPE !== $post->post_type
            || LayoutRepository::is_publishing()
        ) {
            return;
        }

        try {
            ( new LayoutRepository() )->publish( (int) $post->ID );
        } catch ( \InvalidArgumentException $ex ) {
            Logger::exception( $ex, 'transition_post_status', [ 'layout_id' => (int) $post->ID ] );
        }
    }
}
