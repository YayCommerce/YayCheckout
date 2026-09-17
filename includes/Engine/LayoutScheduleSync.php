<?php
namespace YayCheckout\Engine;

use YayCheckout\Helpers\Logger;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the snapshot when cron publishes a scheduled layout.
 *
 * A layout is "published" when it has a snapshot, not when post_status says
 * 'publish'. Core's cron only flips post_status, so without this a scheduled
 * layout would reach its date and still have nothing for shoppers to see.
 *
 * Listens to the future → publish transition, but only while core's
 * publish_future_post cron action is running. That fires once per real
 * transition, so an overlapping cron run that dequeues the same event cannot
 * snapshot twice. LayoutRepository::publish() makes the same transition
 * itself when the merchant publishes early; that never happens inside the
 * cron action, so this cannot re-enter publish().
 */
class LayoutScheduleSync {

    use SingletonTrait;

    protected function __construct() {
        add_action( 'future_to_publish', [ $this, 'snapshot_published_layout' ] );
    }

    /**
     * @param \WP_Post $post
     */
    public function snapshot_published_layout( $post ): void {
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
            // Unusable markup: the previous snapshot (if any) keeps serving,
            // as after a failed manual publish — but unlike that case
            // post_status already reads 'publish'.
            Logger::exception( $ex, 'publish_future_post', [ 'layout_id' => (int) $post->ID ] );
        }
    }
}
