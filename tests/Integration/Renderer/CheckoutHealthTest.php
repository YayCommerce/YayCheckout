<?php
namespace YayCheckout\Tests\Integration\Renderer;

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use YayCheckout\Renderer\CheckoutHealth;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Repositories\CompatibilityCatalogueRepository;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Support\CompatibilityCatalogue;
use YayCheckout\Tests\Support\FakeBlockPaymentMethod;
use YayCheckout\Tests\Support\FakeClassicGateway;
use YayCheckout\Tests\Support\LayoutFactory;

/** The store-level rules that make safe mode hand the checkout back to WooCommerce. */
class CheckoutHealthTest extends \WP_UnitTestCase {

    /** @var array */
    private $catalogue;

    /** @var int */
    private $checkout_page_id;

    public function set_up(): void {
        parent::set_up();

        update_option( 'active_plugins', [ 'woocommerce/woocommerce.php', 'fake-conflict/fake-conflict.php' ] );
        update_option( 'yaycheckout_settings', [ 'general' => [ 'safe_mode' => true ] ] );
        delete_transient( CompatibilityCatalogue::TRANSIENT );
        $this->catalogue = ( new CompatibilityCatalogueRepository() )->cached();
        $this->save_catalogue();

        // A classic checkout page unless a test opts into the Checkout block; the installed page does not survive the test database reset.
        $this->checkout_page_id = self::factory()->post->create(
            [
                'post_type'    => 'page',
                'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
            ]
        );
        update_option( 'woocommerce_checkout_page_id', $this->checkout_page_id );
    }

    public function tear_down(): void {
        remove_all_filters( 'woocommerce_payment_gateways' );
        // init() appends to the loaded list, so clear it before reloading the core gateways.
        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();
        $registry = Package::container()->get( PaymentMethodRegistry::class );
        if ( $registry->is_registered( 'fake_block_only' ) ) {
            $registry->unregister( 'fake_block_only' );
        }

        parent::tear_down();
    }

    public function test_active_conflicting_plugin_is_a_problem(): void {
        $this->catalogue_plugin(
            'fake-conflict',
            [
                'status' => 'conflict',
                'title'  => 'Fake Conflict',
            ]
        );
        $this->assertSame( 'Fake Conflict conflicts with YayCheckout.', CheckoutHealth::store_problem() );
    }

    public function test_inactive_conflicting_plugin_is_not_a_problem(): void {
        $this->catalogue_plugin( 'not-installed', [ 'status' => 'conflict' ] );
        $this->assertSame( '', CheckoutHealth::store_problem() );
    }

    public function test_fixed_in_at_or_below_installed_version_is_not_a_problem(): void {
        $this->catalogue_plugin(
            'woocommerce',
            [
                'status'   => 'conflict',
                'fixed_in' => '1.0.0',
            ]
        );
        $this->assertSame( '', CheckoutHealth::store_problem() );
    }

    public function test_fixed_in_above_installed_version_is_a_problem(): void {
        $this->catalogue_plugin(
            'woocommerce',
            [
                'status'   => 'conflict',
                'fixed_in' => '999.0.0',
            ]
        );
        $this->assertStringEndsWith( 'conflicts with YayCheckout.', CheckoutHealth::store_problem() );
    }

    public function test_only_gateway_marked_conflict_is_a_problem(): void {
        $this->enable_classic_gateway();
        $this->catalogue_gateway( 'fake_classic', [ 'status' => 'conflict' ] );
        $this->assertSame( 'No enabled payment method works in the YayCheckout checkout.', CheckoutHealth::store_problem() );
    }

    public function test_usable_classic_gateway_is_not_a_problem(): void {
        $this->enable_classic_gateway();
        $this->assertSame( '', CheckoutHealth::store_problem() );
    }

    public function test_only_block_payment_on_block_checkout_is_a_problem(): void {
        $this->use_block_checkout_page();
        $this->register_block_payment_method();
        $this->assertSame( 'No enabled payment method works in the YayCheckout checkout.', CheckoutHealth::store_problem() );
    }

    public function test_only_block_payment_on_classic_checkout_is_not_a_problem(): void {
        $this->register_block_payment_method();
        $this->assertSame( '', CheckoutHealth::store_problem() );
    }

    public function test_no_payment_method_at_all_is_not_a_problem(): void {
        $this->use_block_checkout_page();
        $this->assertSame( '', CheckoutHealth::store_problem() );
    }

    public function test_filter_adds_a_problem(): void {
        add_filter( 'yaycheckout_safe_mode_problem', [ $this, 'custom_reason' ] );
        $this->assertSame( 'Custom reason.', CheckoutHealth::store_problem() );
    }

    public function test_layout_markup_problem_comes_first(): void {
        $layout = ( new LayoutRepository() )->find( LayoutFactory::create_draft( [ 'post_content' => '<!-- wp:yaycheckout/checkout /-->' ] ) );
        add_filter( 'yaycheckout_safe_mode_problem', [ $this, 'custom_reason' ] );
        $this->assertSame( 'Add an Email field to this layout before activating it.', CheckoutHealth::problem( $layout ) );
    }

    public function test_safe_mode_on_falls_back_on_a_problem(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        add_filter( 'yaycheckout_safe_mode_problem', [ $this, 'custom_reason' ] );
        $this->assertNull( CheckoutPage::get_instance()->layout() );
    }

    public function test_safe_mode_on_without_a_problem_keeps_the_layout(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        $this->assertNotNull( CheckoutPage::get_instance()->layout() );
    }

    public function test_safe_mode_off_keeps_the_layout(): void {
        update_option( 'yaycheckout_settings', [ 'general' => [ 'safe_mode' => false ] ] );
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        add_filter( 'yaycheckout_safe_mode_problem', [ $this, 'custom_reason' ] );
        $this->assertNotNull( CheckoutPage::get_instance()->layout() );
    }

    public function custom_reason(): string {
        return 'Custom reason.';
    }

    private function catalogue_plugin( string $slug, array $entry ): void {
        $this->catalogue['plugins'][ $slug ] = array_merge( CompatibilityCatalogue::entry_defaults(), $entry );
        $this->save_catalogue();
    }

    private function catalogue_gateway( string $id, array $entry ): void {
        $this->catalogue['gateways'][ $id ] = array_merge( CompatibilityCatalogue::entry_defaults(), $entry );
        $this->save_catalogue();
    }

    private function save_catalogue(): void {
        set_transient( CompatibilityCatalogue::TRANSIENT, $this->catalogue, HOUR_IN_SECONDS );
    }

    private function enable_classic_gateway(): void {
        add_filter(
            'woocommerce_payment_gateways',
            static function ( $gateways ) {
                $gateways[] = new FakeClassicGateway();
                return $gateways;
            }
        );
        WC()->payment_gateways()->init();
    }

    private function use_block_checkout_page(): void {
        wp_update_post(
            [
                'ID'           => $this->checkout_page_id,
                'post_content' => '<!-- wp:woocommerce/checkout /-->',
            ]
        );
    }

    private function register_block_payment_method(): void {
        Package::container()->get( PaymentMethodRegistry::class )->register( new FakeBlockPaymentMethod() );
    }
}
