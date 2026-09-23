<?php
namespace YayCheckout\PostTypes;

use YayCheckout\Controllers\LayoutCoreRestController;
use YayCheckout\Models\Layout;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the checkout layout post type. Registration only: show_ui stays on (the editor screen is embedded in the
 * SPA) and show_in_rest so the editor saves drafts through core's wp/v2.
 */
class LayoutPostType {

    use SingletonTrait;

    protected function __construct() {
        add_action( 'init', [ $this, 'register' ] );
    }

    public function register() {
        register_post_type(
            Layout::POST_TYPE,
            [
                'labels'                => [
                    'name'          => __( 'Checkout Layouts', 'yaycheckout' ),
                    'singular_name' => __( 'Checkout Layout', 'yaycheckout' ),
                ],
                'public'                => false,
                'show_ui'               => true,
                'show_in_menu'          => false,
                'show_in_rest'          => true,
                'rest_base'             => Layout::POST_TYPE,
                // Core would serve every 'publish' layout anonymously; see the controller.
                'rest_controller_class' => LayoutCoreRestController::class,
                'supports'              => [ 'title', 'editor', 'revisions', 'custom-fields', 'author', 'thumbnail', 'page-attributes' ],
                'has_archive'           => false,
                'rewrite'               => false,
                'query_var'             => false,
                'can_export'            => true,
                'delete_with_user'      => false,

                // Every cap → manage_woocommerce, so core's REST and our controllers check the same thing.
                'capability_type'       => 'post',
                'map_meta_cap'          => false,
                'capabilities'          => [
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
                 * Seed a Checkout root on new layouts. Inserting another one
                 * after delete is allowed (no template_lock); `multiple: false`
                 * still permits only one Checkout, at any depth.
                 */
                'template'              => [
                    [ 'yaycheckout/checkout' ],
                ],
            ]
        );
    }
}
