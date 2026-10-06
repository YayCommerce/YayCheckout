<?php
namespace YayCheckout\Engine;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivates a layout that reaches the trash and keeps it there for good.
 *
 * Every trash path (LayoutRepository::archive(), wp-admin, core's wp/v2 DELETE)
 * runs wp_trash_post(), which stamps _wp_trash_meta_time for wp_scheduled_delete
 * to purge after EMPTY_TRASH_DAYS; removing the stamp stops the purge.
 */
class LayoutTrashSync {

    use SingletonTrait;

    protected function __construct() {
        add_action( 'trashed_post', [ $this, 'keep_trashed_layout' ] );
    }

    public function keep_trashed_layout( $post_id ): void {
        if ( Layout::POST_TYPE !== get_post_type( $post_id ) ) {
            return;
        }

        delete_post_meta( $post_id, '_wp_trash_meta_time' );
        ( new LayoutRepository() )->deactivate( (int) $post_id );
    }
}
