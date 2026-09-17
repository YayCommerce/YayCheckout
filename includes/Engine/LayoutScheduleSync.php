<?php
namespace YayCheckout\Engine;

use YayCheckout\Enums\LayoutPostStatus;
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
 * Hooked on publish_future_post rather than transition_post_status:
 * LayoutRepository::publish() moves a scheduled layout to 'publish' itself
 * when the merchant publishes early, and a transition hook would fire from
 * inside that call and snapshot twice. Cron never reaches publish(), so the
 * two paths cannot overlap.
 */
class LayoutScheduleSync {

    use SingletonTrait;

    protected function __construct() {
        // After core's check_and_publish_future_post() at 10.
        add_action( 'publish_future_post', [ $this, 'snapshot_published_layout' ], 20 );
    }

    /**
     * @param int|string $post_id
     */
    public function snapshot_published_layout( $post_id ): void {
        $post = get_post( $post_id );

        /*
         * Core re-reads the post and leaves it 'future' when the time has not
         * come yet, rescheduling itself. Only a layout it actually published
         * needs a snapshot.
         */
        if (
            ! $post instanceof \WP_Post
            || Layout::POST_TYPE !== $post->post_type
            || LayoutPostStatus::PUBLISHED !== $post->post_status
        ) {
            return;
        }

        try {
            ( new LayoutRepository() )->publish( (int) $post->ID );
        } catch ( \InvalidArgumentException $ex ) {
            // Unusable markup: post_status is already 'publish', the previous
            // snapshot (if any) keeps serving, same as a failed manual publish.
            Logger::exception( $ex, 'publish_future_post', [ 'layout_id' => (int) $post->ID ] );
        }
    }
}
