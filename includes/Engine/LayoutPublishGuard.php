<?php
namespace YayCheckout\Engine;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Services\DerivedCacheBuilder;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Rejects a core-REST publish or schedule whose markup the renderer could
 * not serve: the root is not locked, so the editor can delete the Checkout
 * block and hit Publish. Scheduling is checked too because
 * cron (or a past date) turns it into a publish nothing else inspects.
 */
class LayoutPublishGuard {

    use SingletonTrait;

    protected function __construct() {
        // After LayoutCapGuard at 10: the limit check is the cheaper refusal.
        add_filter( 'rest_pre_insert_' . Layout::POST_TYPE, [ $this, 'reject_unusable_markup' ], 20, 2 );
    }

    /**
     * @return \stdClass|\WP_Error
     */
    public function reject_unusable_markup( $prepared_post, $request ) {
        unset( $request );

        if ( is_wp_error( $prepared_post ) ) {
            return $prepared_post;
        }

        // Core only sets post_status when the request changes it, so this is the transition itself.
        $publishing = [ LayoutStatus::PUBLISHED, LayoutStatus::SCHEDULED ];

        if ( ! in_array( $prepared_post->post_status ?? '', $publishing, true ) ) {
            return $prepared_post;
        }

        $current = empty( $prepared_post->ID ) ? null : get_post( $prepared_post->ID );

        $markup = $prepared_post->post_content ?? ( $current ? $current->post_content : '' );

        try {
            DerivedCacheBuilder::build( (string) $markup );
        } catch ( \InvalidArgumentException $ex ) {
            return new \WP_Error( 'yaycheckout_layout_markup_invalid', $ex->getMessage(), [ 'status' => 400 ] );
        }

        return $prepared_post;
    }
}
