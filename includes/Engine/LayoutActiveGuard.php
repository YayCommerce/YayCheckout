<?php
namespace YayCheckout\Engine;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the active set pointing only at layouts shoppers may see.
 *
 * Deactivating must be the merchant's choice and core surfaces cannot warn, so trashing or deleting the
 * active layout is refused: the denied capability hides Move to trash and makes wp/v2 and wp-admin answer
 * 403, and the pre_* filters catch direct wp_trash_post() and wp_delete_post() calls (WP-CLI, other plugins),
 * which check no capability. Moving the active layout to draft or pending deactivates it instead.
 */
class LayoutActiveGuard {

    use SingletonTrait;

    protected function __construct() {
        add_filter( 'user_has_cap', [ $this, 'deny_deleting_active_layout' ], 10, 3 );
        add_filter( 'pre_trash_post', [ $this, 'refuse_for_active_layout' ], 10, 2 );
        add_filter( 'pre_delete_post', [ $this, 'refuse_for_active_layout' ], 10, 2 );
        add_action( 'transition_post_status', [ $this, 'deactivate_when_unpublished' ], 10, 3 );
    }

    /** A scheduled layout stays active: its current content serves until the new date replaces it. */
    public function deactivate_when_unpublished( $new_status, $old_status, $post ): void {
        unset( $old_status );

        if ( in_array( $new_status, LayoutStatus::live(), true ) ) {
            return;
        }

        if ( $this->is_active_layout( $post ) ) {
            // The status already moved; deactivate() would move it again and re-enter this hook.
            ( new LayoutRepository() )->forget_active( (int) $post->ID );
        }
    }

    /**
     * @param string[] $caps Primitive caps delete_post maps to (LayoutPostType::CAPABILITY).
     * @param array    $args [ meta cap, user id, post id ].
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
        return LayoutRepository::is_layout( $post )
            && ( new LayoutRepository() )->is_active( (int) $post->ID );
    }
}
