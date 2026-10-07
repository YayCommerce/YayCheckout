<?php
namespace YayCheckout\Engine;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the layout rules on core's write paths, which skip the plugin's routes: wp/v2 DELETE,
 * Quick Edit, Bulk Edit, revision restore and WP-CLI. Guards the choke points core funnels them
 * through, wp_delete_post() and wp_insert_post(); LayoutCoreRestController keeps its clearer 400
 * for the editor.
 */
class LayoutWriteGuard {

    use SingletonTrait;

    protected function __construct() {
        add_filter( 'pre_delete_post', [ $this, 'refuse_deleting_unarchived' ], 10, 2 );
        add_filter( 'wp_insert_post_data', [ $this, 'keep_layout_rules' ], 10, 2 );
    }

    /**
     * Archive first: LayoutStatus::deletable().
     *
     * @param  mixed $short_circuit Null unless another filter already decided.
     * @return mixed False refuses; null lets core continue.
     */
    public function refuse_deleting_unarchived( $short_circuit, $post ) {
        if ( null === $short_circuit
            && $post instanceof \WP_Post
            && Layout::POST_TYPE === $post->post_type
            && ! LayoutStatus::deletable( $post->post_status )
        ) {
            return false;
        }

        return $short_circuit;
    }

    /**
     * A layout has no private status: it would leave both tabs and the Free limit. A save that would
     * leave a layout live with markup LayoutMarkupReader::publish_error() refuses keeps the live
     * markup when the layout already serves, and otherwise stays a draft.
     *
     * @param array $data    Slashed post data about to be written.
     * @param array $postarr Slashed post data as passed, with the ID on an update.
     */
    public function keep_layout_rules( array $data, array $postarr ): array {
        if ( Layout::POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
            return $data;
        }

        if ( 'private' === $data['post_status'] ) {
            $data['post_status'] = LayoutStatus::DRAFT;
        }

        if ( ! in_array( $data['post_status'], LayoutStatus::live(), true )
            || '' === LayoutMarkupReader::publish_error( wp_unslash( $data['post_content'] ) )
        ) {
            return $data;
        }

        $stored = empty( $postarr['ID'] ) ? null : get_post( (int) $postarr['ID'] );

        if ( $stored instanceof \WP_Post && in_array( $stored->post_status, LayoutStatus::live(), true ) ) {
            $data['post_content'] = wp_slash( $stored->post_content );
        } else {
            $data['post_status'] = LayoutStatus::DRAFT;
        }

        return $data;
    }
}
