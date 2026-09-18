<?php
namespace YayCheckout\Tests\Support;

use YayCheckout\Models\Layout;

/** Fixtures the layout suites share. */
final class LayoutFactory {

    /** The smallest markup DerivedCacheBuilder accepts: the root block alone, in the self-closing form core stores. */
    public static function checkout_markup(): string {
        return '<!-- wp:yaycheckout/checkout /-->';
    }

    /** Valid markup that differs from checkout_markup(), for draft-vs-snapshot cases. */
    public static function changed_markup(): string {
        return '<!-- wp:yaycheckout/checkout --><!-- wp:paragraph --><p>changed</p><!-- /wp:paragraph --><!-- /wp:yaycheckout/checkout -->';
    }

    /** A layout scheduled one day ahead; core registers the publish_future_post event itself. */
    public static function create_scheduled( array $overrides = [] ): int {
        $gmt = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );

        return self::create_draft(
            array_merge(
                [
                    'post_status'   => 'future',
                    'post_date'     => get_date_from_gmt( $gmt ),
                    'post_date_gmt' => $gmt,
                ],
                $overrides
            )
        );
    }

    /**
     * Moves a scheduled layout's date into the past without touching its status, so cron's
     * check_and_publish_future_post() sees it as due. wp_update_post() would flip it to publish itself.
     */
    public static function backdate( int $id ): void {
        global $wpdb;

        $gmt = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- bypassing wp_update_post() is the point.
        $wpdb->update(
            $wpdb->posts,
            [
                'post_date'     => get_date_from_gmt( $gmt ),
                'post_date_gmt' => $gmt,
            ],
            [ 'ID' => $id ]
        );
        clean_post_cache( $id );
    }

    /** The state a non-REST publish of unusable markup leaves behind: status flipped, no snapshot written. */
    public static function mark_published_without_snapshot( int $id ): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- wp_update_post() would fire the transition and snapshot it.
        $wpdb->update( $wpdb->posts, [ 'post_status' => 'publish' ], [ 'ID' => $id ] );
        clean_post_cache( $id );
    }

    /**
     * Counts writes of the published-at commit marker from now on. update_post_meta() overwrites in
     * place, so a row count cannot tell one snapshot from two; the meta actions can.
     *
     * @return callable(): int
     */
    public static function count_publish_marker_writes(): callable {
        $writes = 0;

        $listener = static function ( $meta_id, $object_id, $meta_key ) use ( &$writes ) {
            if ( Layout::META_PUBLISHED_AT === $meta_key ) {
                ++$writes;
            }
        };

        add_action( 'added_post_meta', $listener, 10, 3 );
        add_action( 'updated_post_meta', $listener, 10, 3 );

        return static function () use ( &$writes ) {
            return $writes;
        };
    }

    /** Core REST update as the current user; the embedded editor's save path. */
    public static function rest_update( int $id, array $body ): \WP_REST_Response {
        $request = new \WP_REST_Request( 'POST', '/wp/v2/' . Layout::POST_TYPE . '/' . $id );
        $request->set_body_params( $body );

        return rest_do_request( $request );
    }

    /**
     * @param array $overrides wp_insert_post() fields.
     *
     * @throws \RuntimeException When the insert fails.
     */
    public static function create_draft( array $overrides = [] ): int {
        $id = wp_insert_post(
            array_merge(
                [
                    'post_type'    => Layout::POST_TYPE,
                    'post_status'  => 'draft',
                    'post_title'   => 'Test layout',
                    'post_content' => self::checkout_markup(),
                ],
                $overrides
            ),
            true
        );

        if ( is_wp_error( $id ) ) {
            throw new \RuntimeException( $id->get_error_message() );
        }

        return (int) $id;
    }

    /** A user holding manage_woocommerce, made current. The cap is granted directly: WooCommerce is not loaded. */
    public static function as_shop_manager(): int {
        $user_id = wp_insert_user(
            [
                'user_login' => 'shop_manager_' . wp_generate_password( 6, false ),
                'user_pass'  => wp_generate_password(),
                'role'       => 'subscriber',
            ]
        );

        $user = get_user_by( 'id', $user_id );
        $user->add_cap( 'manage_woocommerce' );
        wp_set_current_user( $user_id );

        return (int) $user_id;
    }
}
