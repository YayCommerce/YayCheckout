<?php
namespace YayCheckout\Engine;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the active set pointing only at layouts shoppers may see.
 * Trash and delete are refused: the capability is denied so the editor hides
 * Move to trash and wp/v2 and wp-admin answer 403, and the pre_* filters catch
 * wp_trash_post() and wp_delete_post() called directly (WP-CLI, other
 * plugins), which check no capability. Deactivation must be the merchant's
 * decision: the SPA disables Archive on the active row, and
 * LayoutRepository::archive() deactivates before it trashes so a direct
 * caller is not refused here. Core surfaces cannot warn, so they refuse.
 * Moving an active layout back to draft or pending is a decision too, so that
 * one deactivates instead of being refused.
 */
class LayoutActiveGuard {

    use SingletonTrait;

    protected function __construct() {
        add_filter( 'user_has_cap', [ $this, 'deny_deleting_active_layout' ], 10, 3 );
        add_filter( 'pre_trash_post', [ $this, 'refuse_for_active_layout' ], 10, 2 );
        add_filter( 'pre_delete_post', [ $this, 'refuse_for_active_layout' ], 10, 2 );
        add_action( 'transition_post_status', [ $this, 'deactivate_when_unpublished' ], 10, 3 );
    }

    /** A scheduled layout stays active: its snapshot still serves until the new date replaces it. */
    public function deactivate_when_unpublished( $new_status, $old_status, $post ): void {
        unset( $old_status );

        if ( in_array( $new_status, [ LayoutStatus::PUBLISHED, LayoutStatus::SCHEDULED ], true ) ) {
            return;
        }

        if ( $this->is_active_layout( $post ) ) {
            ( new LayoutRepository() )->deactivate( (int) $post->ID );
        }
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
