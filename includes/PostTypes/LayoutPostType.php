<?php
namespace YayCheckout\PostTypes;

use YayCheckout\Models\Layout;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registration only. show_ui stays on (the editor screen is embedded in the
 * SPA) and show_in_rest so the editor saves drafts through core's wp/v2.
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
                'show_ui'          => true,
                'show_in_menu'     => false,
                'show_in_rest'     => true,
                'rest_base'        => Layout::POST_TYPE,
                'supports'         => [ 'title', 'editor', 'revisions', 'custom-fields', 'author', 'thumbnail', 'page-attributes' ],
                'has_archive'      => false,
                'rewrite'          => false,
                'query_var'        => false,
                'can_export'       => true,
                'delete_with_user' => false,

                // Every cap → manage_woocommerce, so core's REST and our controllers check the same thing.
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

                // template_lock governs the top level only; inner locking is the checkout block's templateLock.
                'template'         => [
                    [ 'yaycheckout/checkout' ],
                ],
                'template_lock'    => 'all',
            ]
        );
    }

    /**
     * Orders will reference a layout revision id; pruning would silently lose
     * that history months later.
     */
    public function keep_all_revisions( $num, $post ) {
        if ( $post instanceof \WP_Post && Layout::POST_TYPE === $post->post_type ) {
            return -1;
        }

        return $num;
    }
}
