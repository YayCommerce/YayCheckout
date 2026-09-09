<?php
namespace YayCheckout\PostTypes;

use YayCheckout\Models\Layout;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the checkout layout post type.
 *
 * Registration only — no business logic. The layout has no admin UI of its own
 * because the plugin's React app is the interface, but it is exposed to the
 * REST API so the block editor can save drafts through core's own
 * wp/v2/yay_checkout_layout endpoints.
 */
class LayoutPostType {

    use SingletonTrait;

    protected function __construct() {
        add_action( 'init', [ $this, 'register' ] );
        add_filter( 'wp_revisions_to_keep', [ $this, 'keep_all_revisions' ], 10, 2 );
    }

    public function register() {
        register_post_type(
            Layout::POST_TYPE,
            [
                'labels'           => [
                    'name'          => __( 'Checkout Layouts', 'yaycheckout' ),
                    'singular_name' => __( 'Checkout Layout', 'yaycheckout' ),
                ],
                'public'           => false,
                'show_ui'          => false,
                'show_in_menu'     => false,
                'show_in_rest'     => true,
                'rest_base'        => Layout::POST_TYPE,
                'supports'         => [ 'title', 'editor', 'revisions', 'custom-fields' ],
                'has_archive'      => false,
                'rewrite'          => false,
                'query_var'        => false,
                'can_export'       => true,
                'delete_with_user' => false,

                /*
                 * Every capability maps to manage_woocommerce so core's REST
                 * endpoints and this plugin's own controllers check the same
                 * thing. Doing it here rather than through a map_meta_cap
                 * filter keeps the auth rule in one readable place.
                 */
                'capability_type'  => 'post',
                'map_meta_cap'     => false,
                'capabilities'     => [
                    'edit_post'              => 'manage_woocommerce',
                    'read_post'              => 'manage_woocommerce',
                    'delete_post'            => 'manage_woocommerce',
                    'edit_posts'             => 'manage_woocommerce',
                    'edit_others_posts'      => 'manage_woocommerce',
                    'delete_posts'           => 'manage_woocommerce',
                    'delete_others_posts'    => 'manage_woocommerce',
                    'delete_published_posts' => 'manage_woocommerce',
                    'delete_private_posts'   => 'manage_woocommerce',
                    'publish_posts'          => 'manage_woocommerce',
                    'read_private_posts'     => 'manage_woocommerce',
                    'edit_private_posts'     => 'manage_woocommerce',
                    'edit_published_posts'   => 'manage_woocommerce',
                    'create_posts'           => 'manage_woocommerce',
                ],

                /*
                 * One locked root block. template_lock 'all' governs the top
                 * level only: it stops the root being removed, moved or joined
                 * by siblings. What merchants may rearrange inside the layout
                 * is the checkout block's own templateLock attribute.
                 */
                'template'         => [
                    [ 'yaycheckout/checkout' ],
                ],
                'template_lock'    => 'all',
            ]
        );
    }

    /**
     * Keep every revision for layouts.
     *
     * _yaycheckout_published_revision stores a revision post ID, and orders
     * record the layout revision they were placed on. If WP_POST_REVISIONS
     * pruning deleted those rows, support could no longer answer "which layout
     * version produced this order" for exactly the old orders where the
     * question matters. Removing this filter breaks nothing visibly today and
     * loses that history months later, so it is covered by a test.
     *
     * @param  int      $num
     * @param  \WP_Post $post
     * @return int
     */
    public function keep_all_revisions( $num, $post ) {
        if ( $post instanceof \WP_Post && Layout::POST_TYPE === $post->post_type ) {
            return -1;
        }

        return $num;
    }
}
