<?php
namespace YayCheckout\Blocks;

use YayCheckout\Models\Layout;
use YayCheckout\Renderer\CheckoutLogin;
use YayCheckout\Support\Plan;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the editor blocks and decides which editors offer them.
 *
 * Registers from the built assets/dist/blocks/<block>/ directory, so deps and
 * render callback come from build output, not a second PHP declaration.
 * Storefront rendering is not gated here.
 */
class BlockRegistry {

    use SingletonTrait;

    private const CATEGORY = 'yaycheckout';

    /** Named by viewScript in packages/blocks/src/coupon-form/block.json; core enqueues it when the block renders. */
    public const COUPON_FORM_SCRIPT = 'yaycheckout-coupon-form';

    /**
     * Block directories in the order a shopper meets them. The inserter lists
     * blocks in registration order; a built block missing here still
     * registers, after these.
     */
    private const ORDER = [
        'checkout',
        'login',
        'progress',
        'steps',
        'step',
        'step-button',
        'back-button',
        'return-to-cart',
        'email',
        'billing',
        'shipping',
        'field',
        'extra-field',
        'shipping-methods',
        'express-pay',
        'payment',
        'coupon-form',
        'order-summary',
        'order-summary-items',
        'order-summary-totals',
        'place-order',
        'slot',
    ];

    protected function __construct() {
        add_action( 'init', [ $this, 'register_blocks' ] );
        add_filter( 'block_categories_all', [ $this, 'register_category' ], 10, 2 );
        // After other plugins so a later true/list cannot put our blocks back.
        add_filter( 'allowed_block_types_all', [ $this, 'allowed_blocks' ], 999, 2 );
        add_filter( 'wp_theme_json_data_default', [ $this, 'place_order_width_presets' ] );
    }

    /**
     * Core Button's old 25/50/75/100% widths as presets for Place Order's Width control
     * (Styles > Dimensions). WordPress ships no dimension sizes, so the control has no presets
     * without them. Scoped to the block, and in the default origin, so a theme's own presets win.
     *
     * @param  \WP_Theme_JSON_Data $theme_json
     * @return \WP_Theme_JSON_Data
     */
    public function place_order_width_presets( $theme_json ) {
        $sizes = [];

        foreach ( [ 25, 50, 75, 100 ] as $percent ) {
            $sizes[] = [
                'name' => $percent . '%',
                'slug' => (string) $percent,
                'size' => $percent . '%',
            ];
        }

        return $theme_json->update_with(
            [
                'version'  => \WP_Theme_JSON::LATEST_SCHEMA,
                'settings' => [
                    'blocks' => [
                        'yaycheckout/place-order' => [
                            'dimensions' => [ 'dimensionSizes' => $sizes ],
                        ],
                    ],
                ],
            ]
        );
    }

    /** Registers every built block, in ORDER; without a build, registers none. */
    public function register_blocks(): void {
        $this->register_view_scripts();

        $metadata_files = glob( YAYCHECKOUT_PLUGIN_DIR . 'assets/dist/blocks/*/block.json' );
        $built          = [];

        foreach ( is_array( $metadata_files ) ? $metadata_files : [] as $metadata ) {
            $built[ basename( dirname( $metadata ) ) ] = dirname( $metadata );
        }

        foreach ( array_merge( array_intersect_key( array_flip( self::ORDER ), $built ), $built ) as $dir ) {
            register_block_type( $dir );
        }

        // One bundle serves both editions, so the plan reaches it at runtime; PHP supplies the post type and upgrade link so the bundle never redeclares them.
        $editor_data = [
            'isPro'      => Plan::is_pro(),
            'postType'   => Layout::POST_TYPE,
            'upgradeUrl' => YAYCHECKOUT_UPGRADE_URL,
        ];

        wp_add_inline_script(
            generate_block_asset_handle( 'yaycheckout/checkout', 'editorScript' ),
            'window.yayCheckoutBlocks = ' . wp_json_encode( $editor_data ) . ';',
            'before'
        );

        // The login block's editor script does not run after the checkout inline
        // script, so its option flag is printed on its own handle.
        $login_editor_data = [
            'reminderEnabled' => 'yes' === get_option( CheckoutLogin::OPTION_REMINDER ),
            'settingsUrl'     => admin_url( 'admin.php?page=wc-settings&tab=account#woocommerce_enable_checkout_login_reminder' ),
        ];

        wp_add_inline_script(
            generate_block_asset_handle( 'yaycheckout/login', 'editorScript' ),
            'window.yayCheckoutLogin = ' . wp_json_encode( $login_editor_data ) . ';',
            'before'
        );
    }

    /**
     * Storefront scripts that a block.json names by handle, because they depend on WooCommerce
     * handles the blocks build can't declare. wc-checkout supplies wc_checkout_params.
     */
    private function register_view_scripts(): void {
        wp_register_script(
            self::COUPON_FORM_SCRIPT,
            YAYCHECKOUT_PLUGIN_URL . 'assets/js/checkout-coupon-form.js',
            [ 'jquery', 'wc-checkout' ],
            YAYCHECKOUT_VERSION,
            true
        );
    }

    /** Adds the YayCheckout inserter category in the layout editor. */
    public function register_category( $categories, $editor_context ) {
        if ( 'layout' !== $this->editor( $editor_context ) ) {
            return $categories;
        }

        return array_merge(
            [
                [
                    'slug'  => self::CATEGORY,
                    'title' => __( 'YayCheckout', 'yaycheckout' ),
                    'icon'  => null,
                ],
            ],
            $categories
        );
    }

    /**
     * Keeps YayCheckout blocks to the layout editor, and WooCommerce's Checkout block (a second
     * checkout form inside the layout's own) out of it. Types stay registered so checkout-page.php
     * can still do_blocks() the layout. An unknown editor is left alone: it may be the layout editor.
     *
     * @param  bool|string[] $allowed_block_types
     * @return bool|string[]
     */
    public function allowed_blocks( $allowed_block_types, $editor_context ) {
        switch ( $this->editor( $editor_context ) ) {
            case 'layout':
                return self::without( $allowed_block_types, static fn( $name ) => 'woocommerce/checkout' === $name );
            case 'other':
                return self::without( $allowed_block_types, static fn( $name ) => str_starts_with( $name, 'yaycheckout/' ) );
            default:
                return $allowed_block_types;
        }
    }

    /** 'layout', 'other', or '' while Gutenberg has not said which editor this is. */
    private function editor( $editor_context ): string {
        $post      = $editor_context->post ?? null;
        $post_type = $post instanceof \WP_Post ? $post->post_type : '';

        if ( '' === $post_type && function_exists( 'get_current_screen' ) ) {
            $screen    = get_current_screen();
            $post_type = $screen && is_string( $screen->post_type ) ? $screen->post_type : '';
        }

        if ( '' !== $post_type ) {
            return Layout::POST_TYPE === $post_type ? 'layout' : 'other';
        }

        $name = $editor_context->name ?? '';

        return in_array( $name, [ 'core/edit-site', 'core/edit-widgets', 'core/customize-widgets' ], true ) ? 'other' : '';
    }

    /**
     * @param  bool|string[] $allowed_block_types
     * @param  callable      $drop                Takes a block name; true removes it.
     * @return bool|string[]
     */
    private static function without( $allowed_block_types, callable $drop ) {
        if ( true === $allowed_block_types ) {
            // true means "all"; subtract by naming every registered type.
            $allowed_block_types = array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );
        }

        if ( ! is_array( $allowed_block_types ) ) {
            return $allowed_block_types;
        }

        return array_values( array_filter( $allowed_block_types, static fn( $name ) => ! $drop( $name ) ) );
    }
}
