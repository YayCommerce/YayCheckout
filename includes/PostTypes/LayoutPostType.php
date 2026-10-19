<?php
namespace YayCheckout\PostTypes;

use YayCheckout\Controllers\LayoutCoreRestController;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the checkout layout post type. show_ui stays on because the SPA embeds its editor screen, and
 * show_in_rest so the editor saves drafts through core's wp/v2.
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
                // Core would serve every 'publish' layout anonymously.
                'rest_controller_class' => LayoutCoreRestController::class,
                'supports'              => [ 'title', 'editor', 'revisions', 'custom-fields', 'author' ],
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

        // In REST so the editor's Save writes it with the markup; the schema makes core refuse an invalid token.
        // Reads are projected first: core answers null for a stored design missing a section added since.
        register_post_meta(
            Layout::POST_TYPE,
            Layout::META_DESIGN,
            [
                'type'              => 'object',
                'single'            => true,
                'default'           => LayoutDesign::from_meta( [] )->to_meta(),
                'show_in_rest'      => [
                    'schema'           => LayoutDesign::rest_schema(),
                    'prepare_callback' => static function ( $value ) {
                        return LayoutDesign::from_meta( $value )->to_meta();
                    },
                ],
                'sanitize_callback' => static function ( $value ) {
                    return LayoutDesign::from_meta( $value )->to_meta();
                },
                // The key is protected (leading underscore), so core requires an explicit check.
                'auth_callback'     => static function () {
                    return current_user_can( self::CAPABILITY );
                },
            ]
        );

        // In REST so the layout editor's publish guard can skip checkout-required blocks for thank-you layouts.
        // Not on Layout::to_array(): the SPA list schema is strict and has no surface field yet.
        register_post_meta(
            Layout::POST_TYPE,
            Layout::META_SURFACE,
            [
                'type'              => 'string',
                'single'            => true,
                'default'           => Layout::SURFACE_CHECKOUT,
                'show_in_rest'      => true,
                'sanitize_callback' => static function ( $value ) {
                    return Layout::normalize_surface( $value );
                },
                'auth_callback'     => static function () {
                    return current_user_can( self::CAPABILITY );
                },
            ]
        );
    }
}
