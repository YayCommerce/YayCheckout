<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Tests\Support\LayoutFactory;

/** The checkout page template on block and classic themes. */
class CheckoutPageTemplateTest extends WP_UnitTestCase {

    private const PROBE_BLOCK  = 'yaycheckout-test/module-probe';
    private const PROBE_MODULE = 'yaycheckout-test-probe';

    private string $previous_theme = '';

    private array $previous_theme_directories = [];

    public function set_up(): void {
        parent::set_up();

        $this->previous_theme             = get_stylesheet();
        $this->previous_theme_directories = $GLOBALS['wp_theme_directories'];
        register_theme_directory( dirname( __DIR__ ) . '/fixtures/template-themes' );
        wp_clean_themes_cache();
        wc_load_cart();

        register_block_type(
            self::PROBE_BLOCK,
            [
                'render_callback' => static function () {
                    wp_enqueue_script_module( self::PROBE_MODULE, 'https://example.test/probe.js', [ '@wordpress/interactivity' ] );
                    return '<p>probe</p>';
                },
            ]
        );
    }

    public function tear_down(): void {
        unregister_block_type( self::PROBE_BLOCK );
        wp_dequeue_script_module( self::PROBE_MODULE );
        switch_theme( $this->previous_theme );
        $GLOBALS['wp_theme_directories'] = $this->previous_theme_directories; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Undoes register_theme_directory().
        wp_clean_themes_cache();
        LayoutFactory::forget_served_layout();

        parent::tear_down();
    }

    /**
     * The layout sits between the theme's header and footer, and a module a
     * block adds while rendering resolves its imports, or the browser refuses
     * to load it. One render per theme: get_header() and get_footer()
     * require_once their files, so a second classic render prints neither.
     *
     * @dataProvider themes
     */
    public function test_renders_the_layout_inside_the_theme_with_its_modules_mapped( string $theme ): void {
        $html = $this->render_checkout_page( $theme );

        $layout = strpos( $html, '<p>probe</p>' );

        $this->assertNotFalse( $layout );
        $this->assertSame( 1, substr_count( $html, '<p>probe</p>' ) );
        $this->assertGreaterThan( strpos( $html, '</head>' ), $layout );
        $this->assertLessThan( strrpos( $html, '</body>' ), $layout );

        $this->assertSame( 1, preg_match( '#<script[^>]*type="importmap"[^>]*>(.*?)</script>#s', $html, $map ), 'No import map printed.' );
        $this->assertArrayHasKey( '@wordpress/interactivity', json_decode( $map[1], true )['imports'] ?? [] );
    }

    public function themes(): array {
        return [
            'block theme'   => [ 'twentytwentyfive' ],
            'classic theme' => [ 'classic-shell' ],
        ];
    }

    private function render_checkout_page( string $theme ): string {
        switch_theme( $theme );
        $this->hook_script_modules_for_the_current_theme();
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:' . self::PROBE_BLOCK . ' /--><!-- /wp:yaycheckout/checkout -->' );

        ob_start();
        require YAYCHECKOUT_PLUGIN_DIR . 'templates/checkout-page.php';

        return (string) ob_get_clean();
    }

    /**
     * Core picks where to print modules once, at boot, from the theme active
     * then; the suite boots on a classic theme. The test case restores hooks
     * after each test.
     */
    private function hook_script_modules_for_the_current_theme(): void {
        $modules = wp_script_modules();

        foreach ( [ 'print_import_map', 'print_head_enqueued_script_modules', 'print_enqueued_script_modules', 'print_script_module_preloads' ] as $method ) {
            remove_action( 'wp_head', [ $modules, $method ] );
            remove_action( 'wp_footer', [ $modules, $method ] );
        }

        $modules->add_hooks();
    }
}
