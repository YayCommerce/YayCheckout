<?php
namespace YayCheckout\PostTypes;

use YayCheckout\Controllers\LayoutCoreRestController;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the checkout layout post type and its guest view's. show_ui stays on because the SPA embeds its editor screen, and
 * show_in_rest so the editor saves drafts through core's wp/v2.
 */
class LayoutPostType {

    use SingletonTrait;

    /** The one capability every layout action and every YayCheckout REST route requires. */
    public const CAPABILITY = 'manage_woocommerce';

    protected function __construct() {
        add_action( 'init', [ $this, 'register' ] );
        add_filter( 'wp_insert_post_data', [ $this, 'sanitize_guest_view' ] );
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
                'capabilities'          => self::capabilities(),

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

        // What a blocked guest sees: one post per layout (post_parent), edited in core's block editor like the layout.
        // Always published (LayoutRepository::save_guest_view()); LayoutCoreRestController gates reads and applies its publish rule to layouts only.
        register_post_type(
            Layout::GUEST_VIEW_POST_TYPE,
            [
                'labels'                => [
                    'name'          => __( 'Guest views', 'yaycheckout' ),
                    'singular_name' => __( 'Guest view', 'yaycheckout' ),
                ],
                'public'                => false,
                'show_ui'               => true,
                'show_in_menu'          => false,
                'show_in_rest'          => true,
                'rest_base'             => Layout::GUEST_VIEW_POST_TYPE,
                'rest_controller_class' => LayoutCoreRestController::class,
                'supports'              => [ 'title', 'editor', 'revisions' ],
                'has_archive'           => false,
                'rewrite'               => false,
                'query_var'             => false,
                'can_export'            => true,
                'delete_with_user'      => false,
                'capability_type'       => 'post',
                'map_meta_cap'          => false,
                'capabilities'          => self::capabilities(),
            ]
        );
    }

    /**
     * Every write of a guest view, from the editor, a revision restore or LayoutRepository, keeps only
     * Layout::GUEST_VIEW_BLOCKS. $data arrives slashed.
     *
     * @param array $data
     * @return array
     */
    public function sanitize_guest_view( $data ) {
        if ( Layout::GUEST_VIEW_POST_TYPE === ( $data['post_type'] ?? '' ) && isset( $data['post_content'] ) ) {
            $data['post_content'] = wp_slash( LayoutMarkupReader::sanitize_guest_view( wp_unslash( $data['post_content'] ) ) );
        }

        return $data;
    }

    /** @return array<string, string> Every cap a layout or guest view checks, mapped to CAPABILITY. */
    private static function capabilities(): array {
        return array_fill_keys(
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
        );
    }
}
