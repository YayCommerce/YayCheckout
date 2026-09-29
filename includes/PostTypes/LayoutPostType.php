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

    /** The one capability every layout action and every YayCheckout REST route requires. */
    public const CAPABILITY = 'manage_woocommerce';

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

                // Every cap → CAPABILITY, so core's REST and our controllers check the same thing.
                'capability_type'       => 'post',
                'map_meta_cap'          => false,
                'capabilities'          => array_fill_keys(
                    [
                        'edit_post',
                        'read_post',
                        'delete_post',
                        'edit_posts',
                        'edit_others_posts',
                        'delete_posts',
                        'delete_others_posts',
                        'delete_published_posts',
                        'delete_private_posts',
                        'publish_posts',
                        'read_private_posts',
                        'edit_private_posts',
                        'edit_published_posts',
                        'create_posts',
                    ],
                    self::CAPABILITY
                ),

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
