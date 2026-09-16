<?php
namespace YayCheckout\Engine;

use YayCheckout\Enums\LayoutPostStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the block editor's Publish button through the real publish sequence.
 *
 * The editor saves a layout through core's wp/v2 endpoints, and those carry a
 * status alongside the content. Its Publish button sends 'publish' — the one
 * status no LayoutStatus maps to, so LayoutRepository::query() stopped matching
 * the post and the layout dropped out of every tab with its row intact in the
 * database. Every other status core can send is one LayoutPostStatus holds.
 *
 * Publishing is a sequence (snapshot, derived caches, commit marker), not a
 * field, so that one value is held back before the write and replayed through
 * LayoutRepository after it, where the content core just saved is the content
 * being snapshotted.
 */
class LayoutStatusGuard {

    use SingletonTrait;

    /** What core's editor sends when the merchant asks to publish. */
    private const PUBLISH_INTENT = 'publish';

    protected function __construct() {
        add_filter( 'rest_pre_insert_' . Layout::POST_TYPE, [ $this, 'hold_publish_intent' ] );
        add_action( 'rest_after_insert_' . Layout::POST_TYPE, [ $this, 'publish_on_intent' ], 10, 2 );
    }

    /**
     * @param  \stdClass $prepared_post
     * @return \stdClass
     */
    public function hold_publish_intent( $prepared_post ) {
        if ( ! isset( $prepared_post->post_status ) || self::PUBLISH_INTENT !== $prepared_post->post_status ) {
            return $prepared_post;
        }

        // Keep the layout where it is; publish_on_intent moves it, or nothing does.
        $stored = empty( $prepared_post->ID ) ? '' : get_post_status( $prepared_post->ID );

        $prepared_post->post_status = $stored ? $stored : LayoutPostStatus::DRAFT;

        return $prepared_post;
    }

    /**
     * @param  \WP_Post         $post
     * @param  \WP_REST_Request $request
     * @return void
     */
    public function publish_on_intent( $post, $request ) {
        if ( self::PUBLISH_INTENT !== $request->get_param( 'status' ) ) {
            return;
        }

        try {
            ( new LayoutRepository() )->publish( (int) $post->ID );
        } catch ( \InvalidArgumentException $ex ) {
            /*
             * Unusable markup is the merchant's to fix, not a fault to log. The
             * content is saved and the layout stays put, the same outcome the
             * Layouts screen's Publish button reports as a 400.
             */
            return;
        }

        /*
         * Core builds the response from the WP_Post it read before this hook and
         * never re-reads it, so without this the editor is told the layout is
         * still a draft and reports "Draft saved." over a published layout.
         */
        $post->post_status = LayoutPostStatus::PUBLISHED;
    }
}
