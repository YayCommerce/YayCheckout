<?php
namespace YayCheckout\Blocks;

use YayCheckout\Engine\Register\ScriptName;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Renderer\CheckoutDifferentAddress;
use YayCheckout\Renderer\CheckoutLogin;
use YayCheckout\Renderer\HideWhenEmpty;
use YayCheckout\Renderer\LiveValues;
use YayCheckout\Renderer\PinToBottom;
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

    /** Named by viewScript in packages/blocks/src/order-summary-items/block.json; drives the quantity stepper. */
    public const ORDER_SUMMARY_ITEMS_SCRIPT = 'yaycheckout-order-summary-items';

    /** Named by viewScript in packages/blocks/src/different-address/block.json; hides and mirrors Billing when shipping is main. */
    public const DIFFERENT_ADDRESS_SCRIPT = 'yaycheckout-different-address';

    /**
     * Block directories in the order a shopper meets them. The inserter lists
     * blocks in registration order; a built block missing here still
     * registers, after these.
     */
    private const ORDER = [
        'checkout',
        'login',
        'steps-progress',
        'steps',
        'step',
        'step-button',
        'back-button',
        'return-to-cart',
        'go-to-step',
        'email',
        'create-account',
        'billing',
        'shipping',
        'different-address',
        'field',
        'extra-field',
        'order-note',
        'additional-fields',
        'shipping-methods',
        'express-pay',
        'payment',
        'coupon-form',
        'coupon-button',
        'order-summary',
        'order-summary-header',
        'order-summary-details',
        'order-summary-toggle',
        'order-summary-items',
        'order-summary-totals',
        'order-summary-total',
        'field-value',
        'place-order',
        'slot',
    ];

    protected function __construct() {
        add_action( 'init', [ $this, 'register_blocks' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'editor_design_data' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_live_value_editor' ] );
        add_action( 'enqueue_block_assets', [ $this, 'enqueue_live_value_editor_style' ] );
        add_filter( 'block_categories_all', [ $this, 'register_category' ], 10, 2 );
        // After other plugins so a later true/list cannot put our blocks back.
        add_filter( 'allowed_block_types_all', [ $this, 'allowed_blocks' ], 999, 2 );
        add_filter( 'wp_theme_json_data_default', [ $this, 'block_settings' ] );
    }

    /**
     * Block settings in the default origin, so a theme's own block settings and presets win:
     * - Place Order: Core Button's old 25/50/75/100% widths as presets for its Width control
     *   (Styles > Dimensions). WordPress ships no dimension sizes, so the control has none without them.
     * - Every block in ORDER: padding, margin and border, and Steps, Step, Billing, Shipping, Order Summary and Summary Details: blockGap,
     *   which core's defaults leave off, so their Styles controls show on themes that don't turn them on.
     *
     * @param  \WP_Theme_JSON_Data $theme_json
     * @return \WP_Theme_JSON_Data
     */
    public function block_settings( $theme_json ) {
        $sizes = [];

        foreach ( [ 25, 50, 75, 100 ] as $percent ) {
            $sizes[] = [
                'name' => $percent . '%',
                'slug' => (string) $percent,
                'size' => $percent . '%',
            ];
        }

        $blocks = [];

        foreach ( self::ORDER as $block ) {
            $blocks[ 'yaycheckout/' . $block ] = [
                'spacing' => [
                    'padding' => true,
                    'margin'  => true,
                ],
                'border'  => [
                    'color'  => true,
                    'radius' => true,
                    'style'  => true,
                    'width'  => true,
                ],
            ];
        }

        $blocks['yaycheckout/place-order']['dimensions'] = [ 'dimensionSizes' => $sizes ];

        foreach ( [ 'steps', 'step', 'billing', 'shipping', 'order-summary', 'order-summary-details' ] as $gap_block ) {
            $blocks[ 'yaycheckout/' . $gap_block ]['spacing']['blockGap'] = true;
        }

        return $theme_json->update_with(
            [
                'version'  => \WP_Theme_JSON::LATEST_SCHEMA,
                'settings' => [ 'blocks' => $blocks ],
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

        self::inline_data( generate_block_asset_handle( 'yaycheckout/checkout', 'editorScript' ), 'window.yayCheckoutBlocks', $editor_data );

        // The login block's editor script does not run after the checkout inline
        // script, so its option flag is printed on its own handle.
        $login_editor_data = [
            'reminderEnabled' => CheckoutLogin::reminder_enabled(),
            'settingsUrl'     => admin_url( 'admin.php?page=wc-settings&tab=account#woocommerce_enable_checkout_login_reminder' ),
        ];

        self::inline_data( generate_block_asset_handle( 'yaycheckout/login', 'editorScript' ), 'window.yayCheckoutLogin', $login_editor_data );

        // The create-account editor script may not run after the checkout inline
        // script, so its option flag is printed on its own handle.
        $create_account_editor_data = [
            'signupEnabled' => 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ),
            'settingsUrl'   => admin_url( 'admin.php?page=wc-settings&tab=account#woocommerce_enable_signup_and_login_from_checkout' ),
        ];

        self::inline_data( generate_block_asset_handle( 'yaycheckout/create-account', 'editorScript' ), 'window.yayCheckoutCreateAccount', $create_account_editor_data );

        // The different-address editor script may not run after the checkout inline
        // script, so WooCommerce's Shipping destination is printed on its own handle.
        $different_address_editor_data = [
            'destination' => CheckoutDifferentAddress::destination(),
            'copiedKeys'  => CheckoutDifferentAddress::COPIED_KEYS,
            'settingsUrl' => admin_url( 'admin.php?page=wc-settings&tab=shipping&section=options' ),
        ];

        self::inline_data( generate_block_asset_handle( 'yaycheckout/different-address', 'editorScript' ), 'window.yayCheckoutDifferentAddress', $different_address_editor_data );
    }

    /**
     * Adds `design` to window.yayCheckoutBlocks: LayoutDesign's rules, which the Layout design sidebar edits
     * the layout's design with. Only in the block editor, since register_blocks() runs on every request and
     * nothing else reads it.
     */
    public function editor_design_data(): void {
        $design = [
            'metaKey'           => Layout::META_DESIGN,
            // The id WordPress gives this handle's inline style, which holds the printed design.
            'styleId'           => ScriptName::STYLE_CHECKOUT . '-inline-css',
            'schema'            => LayoutDesign::schema(),
            'fontFamilies'      => LayoutDesign::FONT_FAMILIES,
            'serifFonts'        => LayoutDesign::SERIF_FONTS,
            // The id WordPress gives this handle's inline style, which holds the font faces.
            'fontStyleId'       => ScriptName::STYLE_CHECKOUT_FONTS . '-inline-css',
            'customCssMaxBytes' => LayoutDesign::CUSTOM_CSS_MAX_BYTES,
        ];

        // Added after register_blocks()' own inline script, so window.yayCheckoutBlocks exists.
        self::inline_data( generate_block_asset_handle( 'yaycheckout/checkout', 'editorScript' ), 'window.yayCheckoutBlocks.design', $design );
        // The live value picker's Cart totals and Counts.
        self::inline_data( generate_block_asset_handle( 'yaycheckout/checkout', 'editorScript' ), 'window.yayCheckoutBlocks.liveValues', LiveValues::cart_values() );
    }

    /**
     * The live value format, in the layout editor only: a page's Paragraph never offers it. It runs after the
     * Checkout editor script, whose inline data it reads. Skipped without a build.
     */
    public function enqueue_live_value_editor(): void {
        $asset = 'layout' === $this->editor( null ) ? self::built_asset( 'live-value/index' ) : null;

        if ( null === $asset ) {
            return;
        }

        wp_enqueue_script(
            LiveValues::EDITOR_SCRIPT,
            $asset['url'] . '.js',
            array_merge( $asset['dependencies'], [ generate_block_asset_handle( 'yaycheckout/checkout', 'editorScript' ) ] ),
            $asset['version'],
            true
        );
        wp_set_script_translations( LiveValues::EDITOR_SCRIPT, 'yaycheckout', YAYCHECKOUT_PLUGIN_DIR . 'languages' );
    }

    /** The live value chip style, on enqueue_block_assets so it reaches the canvas iframe. Layout editor only; skipped without a build. */
    public function enqueue_live_value_editor_style(): void {
        $asset = is_admin() && 'layout' === $this->editor( null ) ? self::built_asset( 'live-value/index', true ) : null;

        if ( null !== $asset ) {
            wp_enqueue_style( LiveValues::EDITOR_STYLE, $asset['url'] . '.css', [], $asset['version'] );
        }
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

        wp_register_script(
            self::ORDER_SUMMARY_ITEMS_SCRIPT,
            YAYCHECKOUT_PLUGIN_URL . 'assets/js/checkout-order-summary-items.js',
            [ 'jquery', 'wc-checkout' ],
            YAYCHECKOUT_VERSION,
            true
        );

        wp_register_script(
            self::DIFFERENT_ADDRESS_SCRIPT,
            YAYCHECKOUT_PLUGIN_URL . 'assets/js/checkout-different-address.js',
            [ 'jquery', 'wc-checkout', 'wc-address-i18n' ],
            YAYCHECKOUT_VERSION,
            true
        );

        self::inline_data(
            self::DIFFERENT_ADDRESS_SCRIPT,
            'window.yayCheckoutSameBilling',
            [
                'field'      => CheckoutDifferentAddress::SAME_BILLING_FIELD,
                'copiedKeys' => CheckoutDifferentAddress::COPIED_KEYS,
            ]
        );

        // Enqueued by HideWhenEmpty::filter_container() for a container that watches blocks.
        wp_register_script(
            HideWhenEmpty::SCRIPT,
            YAYCHECKOUT_PLUGIN_URL . 'assets/js/checkout-hide-when-empty.js',
            [ 'jquery', 'wc-checkout', 'wc-address-i18n' ],
            YAYCHECKOUT_VERSION,
            true
        );

        // Enqueued by PinToBottom::filter_group() for a pinned Group.
        wp_register_script( PinToBottom::SCRIPT, YAYCHECKOUT_PLUGIN_URL . 'assets/js/checkout-pin-to-bottom.js', [], YAYCHECKOUT_VERSION, true );

        // Enqueued by LiveValues::fill() when an allow-listed block holds a live value. Skipped without a build.
        $live_value = self::built_asset( 'live-value/view', true );

        if ( null !== $live_value ) {
            wp_register_script( LiveValues::SCRIPT, $live_value['url'] . '.js', array_merge( $live_value['dependencies'], [ 'jquery' ] ), $live_value['version'], true );
            wp_register_style( LiveValues::STYLE, $live_value['url'] . '.css', [], $live_value['version'] );
        }
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

    /**
     * A webpack entry no block.json names (packages/blocks/webpack.config.js), from its built asset file: its
     * URL without the extension, dependencies and version. Null without a build, or without its CSS when
     * $with_css.
     *
     * @return array{url: string, dependencies: string[], version: string}|null
     */
    private static function built_asset( string $entry, bool $with_css = false ): ?array {
        $path = YAYCHECKOUT_PLUGIN_DIR . 'assets/dist/blocks/' . $entry;

        if ( ! is_readable( $path . '.asset.php' ) || ( $with_css && ! is_readable( $path . '.css' ) ) ) {
            return null;
        }

        $asset = require $path . '.asset.php';

        return [
            'url'          => YAYCHECKOUT_PLUGIN_URL . 'assets/dist/blocks/' . $entry,
            'dependencies' => $asset['dependencies'],
            'version'      => $asset['version'],
        ];
    }

    /** Prints $data as $variable (window.x or window.x.y) before $handle runs. */
    private static function inline_data( string $handle, string $variable, array $data ): void {
        wp_add_inline_script( $handle, $variable . ' = ' . wp_json_encode( $data ) . ';', 'before' );
    }
}
