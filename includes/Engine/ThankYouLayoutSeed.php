<?php
namespace YayCheckout\Engine;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\PostTypes\LayoutPostType;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures one thank-you layout draft exists so merchants can edit and activate it
 * before the Layouts list has a surface filter or Add thank-you action.
 */
class ThankYouLayoutSeed {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'admin_init', [ $this, 'maybe_seed' ] );
    }

    public function maybe_seed(): void {
        if ( ! current_user_can( LayoutPostType::CAPABILITY ) ) {
            return;
        }

        if ( $this->thank_you_layout_exists() ) {
            return;
        }

        $content = '<!-- wp:heading -->'
            . '<h2 class="wp-block-heading">' . __( 'Thank you', 'yaycheckout' ) . '</h2>'
            . '<!-- /wp:heading -->'
            . '<!-- wp:paragraph -->'
            . '<p>' . __( 'Your order has been received.', 'yaycheckout' ) . '</p>'
            . '<!-- /wp:paragraph -->';

        // wp_insert_post, not the post type template: that template seeds a Checkout block for new layouts.
        wp_insert_post(
            wp_slash(
                [
                    'post_type'    => Layout::POST_TYPE,
                    'post_status'  => LayoutStatus::DRAFT,
                    'post_title'   => __( 'Thank you', 'yaycheckout' ),
                    'post_content' => $content,
                    'meta_input'   => [
                        Layout::META_SURFACE => Layout::SURFACE_THANK_YOU,
                        Layout::META_DESIGN  => LayoutDesign::from_meta( [] )->to_meta(),
                    ],
                ]
            ),
            true
        );
    }

    /** True when any layout post, archived ones included, already has the thank-you surface. */
    private function thank_you_layout_exists(): bool {
        $query = new \WP_Query(
            [
                'post_type'              => Layout::POST_TYPE,
                'post_status'            => LayoutStatus::all(),
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-shot seed existence check, not a storefront query.
                'meta_key'               => Layout::META_SURFACE,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bound to META_SURFACE above.
                'meta_value'             => Layout::SURFACE_THANK_YOU,
            ]
        );

        return $query->have_posts();
    }
}
