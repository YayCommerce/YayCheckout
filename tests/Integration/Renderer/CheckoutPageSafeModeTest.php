<?php
namespace YayCheckout\Tests\Integration\Renderer;

use YayCheckout\Renderer\CheckoutFragments;
use YayCheckout\Renderer\CheckoutHealth;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

/** A layout that fails to render hands this request and the next ones to WooCommerce's checkout. */
class CheckoutPageSafeModeTest extends \WP_UnitTestCase {

    private const BOOM = 'yaycheckout-test/boom';

    public function set_up(): void {
        parent::set_up();

        wc_load_cart();
        update_option( 'yaycheckout_settings', [ 'general' => [ 'safe_mode' => true ] ] );
        delete_option( CheckoutPage::OPTION_RENDER_FAILURE );
        register_block_type(
            self::BOOM,
            [
                'render_callback' => static function () {
                    echo '<p class="half-rendered">';
                    throw new \RuntimeException( 'boom' );
                },
            ]
        );
    }

    public function tear_down(): void {
        unregister_block_type( self::BOOM );
        LayoutFactory::forget_served_layout();

        parent::tear_down();
    }

    public function test_throwing_block_falls_back_and_records(): void {
        $id    = LayoutFactory::serve( self::boom_markup() );
        $level = ob_get_level();

        ob_start();
        $template = CheckoutPage::get_instance()->page_template( '/theme/page.php' );
        $echoed   = ob_get_clean();

        $this->assertSame( '/theme/page.php', $template );
        $this->assertSame( $level, ob_get_level() );
        $this->assertSame( '', $echoed );
        $this->assertNull( CheckoutPage::get_instance()->layout() );

        $failure = get_option( CheckoutPage::OPTION_RENDER_FAILURE );
        $this->assertSame( $id, $failure['layout_id'] );
        $this->assertSame( 'boom', $failure['reason'] );
        $this->assertSame( md5( get_post( $id )->post_content ), $failure['hash'] );
    }

    public function test_next_request_after_failure_falls_back(): void {
        LayoutFactory::serve( self::boom_markup() );
        CheckoutPage::get_instance()->page_template( '/theme/page.php' );
        LayoutFactory::forget_served_layout();

        $this->assertNull( CheckoutPage::get_instance()->layout() );
    }

    public function test_content_change_resets_breaker(): void {
        $id = LayoutFactory::serve( self::boom_markup() );
        CheckoutPage::get_instance()->page_template( '/theme/page.php' );
        wp_update_post(
            [
                'ID'           => $id,
                'post_content' => LayoutFactory::checkout_markup(),
            ]
        );
        LayoutFactory::forget_served_layout();

        $this->assertNotNull( CheckoutPage::get_instance()->layout() );
    }

    public function test_render_without_place_order_button_falls_back(): void {
        add_filter( 'render_block_yaycheckout/place-order', '__return_empty_string' );
        LayoutFactory::serve( LayoutFactory::checkout_markup() );

        $this->assertSame( '/theme/page.php', CheckoutPage::get_instance()->page_template( '/theme/page.php' ) );
        $this->assertSame( 'The layout rendered without a Place order button.', get_option( CheckoutPage::OPTION_RENDER_FAILURE )['reason'] );
    }

    public function test_healthy_render_takes_over(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );

        $this->assertSame( YAYCHECKOUT_PLUGIN_DIR . 'templates/checkout-page.php', CheckoutPage::get_instance()->page_template( '/theme/page.php' ) );
        $this->assertStringContainsString( 'id="place_order"', CheckoutPage::get_instance()->html() );
        $this->assertFalse( get_option( CheckoutPage::OPTION_RENDER_FAILURE ) );
    }

    public function test_safe_mode_off_rethrows_and_records(): void {
        update_option( 'yaycheckout_settings', [ 'general' => [ 'safe_mode' => false ] ] );
        LayoutFactory::serve( self::boom_markup() );

        try {
            CheckoutPage::get_instance()->page_template( '/theme/page.php' );
            $this->fail( 'Expected the exception to propagate.' );
        } catch ( \RuntimeException $e ) {
            $this->assertSame( 'boom', get_option( CheckoutPage::OPTION_RENDER_FAILURE )['reason'] );
        }
    }

    public function test_render_failure_reports_only_while_it_applies(): void {
        $id = LayoutFactory::serve( self::boom_markup() );
        CheckoutPage::get_instance()->page_template( '/theme/page.php' );
        $this->assertSame( 'boom', CheckoutHealth::reported_problem() );

        ( new LayoutRepository() )->deactivate( $id );
        $this->assertSame( '', CheckoutHealth::reported_problem() );
    }

    public function test_fragment_failure_sends_woocommerce_fragments_and_records(): void {
        $id = LayoutFactory::serve( LayoutFactory::checkout_markup() );
        add_action( 'woocommerce_review_order_before_payment', [ self::class, 'throw_fragboom' ] );
        $fragments = [ '.woocommerce-checkout-payment' => '<div>stock</div>' ];

        $result = CheckoutFragments::get_instance()->fragments( $fragments );

        $this->assertSame( $fragments, $result );
        $this->assertSame( 'fragboom', get_option( CheckoutPage::OPTION_RENDER_FAILURE )['reason'] );
        $this->assertSame( $id, get_option( CheckoutPage::OPTION_RENDER_FAILURE )['layout_id'] );
        LayoutFactory::forget_served_layout();
        $this->assertNull( CheckoutPage::get_instance()->layout() );
    }

    public function test_fragment_failure_rethrows_with_safe_mode_off(): void {
        update_option( 'yaycheckout_settings', [ 'general' => [ 'safe_mode' => false ] ] );
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        add_action( 'woocommerce_review_order_before_payment', [ self::class, 'throw_fragboom' ] );

        try {
            CheckoutFragments::get_instance()->fragments( [] );
            $this->fail( 'Expected the exception to propagate.' );
        } catch ( \RuntimeException $e ) {
            $this->assertSame( 'fragboom', get_option( CheckoutPage::OPTION_RENDER_FAILURE )['reason'] );
        }
    }

    public function test_recorded_reason_is_truncated(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        add_filter( 'render_block_yaycheckout/place-order', [ self::class, 'throw_long' ] );

        CheckoutPage::get_instance()->page_template( '/theme/page.php' );

        $this->assertSame( 500, mb_strlen( get_option( CheckoutPage::OPTION_RENDER_FAILURE )['reason'] ) );
    }

    public function test_request_ending_mid_render_is_recorded(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        $page = CheckoutPage::get_instance();
        $flag = new \ReflectionProperty( CheckoutPage::class, 'rendering' );
        $flag->setAccessible( true );

        $page->record_fatal( $page->layout() );
        $this->assertFalse( get_option( CheckoutPage::OPTION_RENDER_FAILURE ), 'A request that finished rendering records nothing.' );

        $flag->setValue( $page, true );
        $page->record_fatal( $page->layout() );
        $flag->setValue( $page, false );

        $this->assertSame( 'The layout stopped the page while rendering.', get_option( CheckoutPage::OPTION_RENDER_FAILURE )['reason'] );
    }

    /**
     * Hooked to the action the Payment fragment fires, so the fragment throws.
     *
     * @throws \RuntimeException Always.
     */
    public static function throw_fragboom(): void {
        throw new \RuntimeException( 'fragboom' );
    }

    /**
     * Hooked to a block render, so the layout fails with a message past the length limit.
     *
     * @throws \RuntimeException Always.
     */
    public static function throw_long(): void {
        throw new \RuntimeException( str_repeat( 'é', 1000 ) );
    }

    private static function boom_markup(): string {
        return str_replace( '<!-- /wp:yaycheckout/checkout -->', '<!-- wp:' . self::BOOM . ' /--><!-- /wp:yaycheckout/checkout -->', LayoutFactory::checkout_markup() );
    }
}
