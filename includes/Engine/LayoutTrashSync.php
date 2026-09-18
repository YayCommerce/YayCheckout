<?php
namespace YayCheckout\Engine;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a trashed layout out of the active set and out of WP's trash purge.
 *
 * Every path into the trash — LayoutRepository::archive(), wp-admin, core's
 * wp/v2 DELETE — goes through wp_trash_post(), which stamps
 * _wp_trash_meta_time so wp_scheduled_delete erases the post after
 * EMPTY_TRASH_DAYS. Archived means kept, on every path.
 */
class LayoutTrashSync {

    use SingletonTrait;

    protected function __construct() {
        add_action( 'trashed_post', [ $this, 'keep_trashed_layout' ] );
    }

    /**
     * @param int $post_id
     */
    public function keep_trashed_layout( $post_id ): void {
        if ( Layout::POST_TYPE !== get_post_type( $post_id ) ) {
            return;
        }

        delete_post_meta( $post_id, '_wp_trash_meta_time' );
        ( new LayoutRepository() )->deactivate( (int) $post_id );
    }
}
