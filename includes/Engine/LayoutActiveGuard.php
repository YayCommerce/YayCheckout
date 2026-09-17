<?php
namespace YayCheckout\Engine;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Refuses to trash or delete the active layout.
 * The capability is denied so the editor hides Move to trash and wp/v2 and
 * wp-admin answer 403; the pre_* filters catch wp_trash_post() and
 * wp_delete_post() called directly (WP-CLI, other plugins), which check no
 * capability. Deactivating first is the merchant's decision, not a side effect.
 */
class LayoutActiveGuard {

    use SingletonTrait;

    protected function __construct() {
        add_filter( 'user_has_cap', [ $this, 'deny_deleting_active_layout' ], 10, 3 );
        add_filter( 'pre_trash_post', [ $this, 'refuse_for_active_layout' ], 10, 2 );
        add_filter( 'pre_delete_post', [ $this, 'refuse_for_active_layout' ], 10, 2 );
    }

    /**
     * @param array    $allcaps The user's capabilities for this check.
     * @param string[] $caps    Primitive caps delete_post maps to (manage_woocommerce).
     * @param array    $args    [ meta cap, user id, post id ].
     */
    public function deny_deleting_active_layout( array $allcaps, array $caps, array $args ): array {
        if ( 'delete_post' !== ( $args[0] ?? '' ) || empty( $args[2] ) ) {
            return $allcaps;
        }

        if ( $this->is_active_layout( get_post( (int) $args[2] ) ) ) {
            foreach ( $caps as $cap ) {
                $allcaps[ $cap ] = false;
            }
        }

        return $allcaps;
    }

    /**
     * @param  mixed $short_circuit Null unless another filter already decided.
     * @return mixed False refuses; null lets core continue.
     */
    public function refuse_for_active_layout( $short_circuit, $post ) {
        if ( null === $short_circuit && $this->is_active_layout( $post ) ) {
            return false;
        }

        return $short_circuit;
    }

    private function is_active_layout( $post ): bool {
        return $post instanceof \WP_Post
            && Layout::POST_TYPE === $post->post_type
            && ( new LayoutRepository() )->is_active( (int) $post->ID );
    }
}
