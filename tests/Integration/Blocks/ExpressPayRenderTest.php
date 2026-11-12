<?php
namespace YayCheckout\Tests\Integration\Blocks;

use WP_UnitTestCase;
use YayCheckout\Renderer\ExpressPay;
use YayCheckout\Tests\Support\LayoutFactory;

/** P-2: the Express Pay block prints adopted wallet buttons, and nothing when no gateway offers any. */
class ExpressPayRenderTest extends WP_UnitTestCase {

    private const BILLING_HOOK = 'woocommerce_checkout_billing';

    private const LAYOUT = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/express-pay /-->%s<!-- /wp:yaycheckout/checkout -->';

    /** @var array ExpressPay's registrations before the test, FunnelKitStripe's among them. */
    private array $registered = [];

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        $this->registered = $this->adopted()->getValue();
    }

    public function tear_down(): void {
        $this->adopted()->setValue( null, $this->registered );
        LayoutFactory::forget_served_layout();
        parent::tear_down();
    }

    public function test_adopted_callback_moves_to_express_hook_unless_a_hook_block_places_it(): void {
        $this->hook_wallet();
        LayoutFactory::serve( sprintf( self::LAYOUT, '' ) );

        $this->assertTrue( ExpressPay::has_buttons() );
        ExpressPay::adopt_into_block();

        $this->assertSame( 5, has_action( ExpressPay::HOOK, [ $this, 'print_wallet' ] ) );
        $this->assertFalse( has_action( self::BILLING_HOOK, [ $this, 'print_wallet' ] ) );
        $this->assertTrue( ExpressPay::has_buttons() );
        $this->assertStringContainsString( 'WALLET', do_blocks( sprintf( self::LAYOUT, '' ) ) );

        remove_action( ExpressPay::HOOK, [ $this, 'print_wallet' ], 5 );
        add_action( self::BILLING_HOOK, [ $this, 'print_wallet' ], 5 );
        LayoutFactory::serve( sprintf( self::LAYOUT, '<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_checkout_billing"} /-->' ) );
        ExpressPay::adopt_into_block();

        $this->assertSame( 5, has_action( self::BILLING_HOOK, [ $this, 'print_wallet' ] ) );
        $this->assertFalse( has_action( ExpressPay::HOOK, [ $this, 'print_wallet' ] ) );
    }

    /** WooCommerce's order attribution prints its inputs on AFTER_HOOK, so the block prints, marked empty, which style.css hides along with the "OR" divider. */
    public function test_block_prints_nothing_without_express_buttons(): void {
        $html = do_blocks( '<!-- wp:yaycheckout/express-pay /-->' );

        $this->assertDoesNotMatchRegularExpression( '#<(button|iframe)\b#', $html );
        $this->assertStringContainsString( 'data-yaycheckout-empty="1"', $html );
    }

    public function print_wallet(): void {
        echo '<div id="wallet">WALLET</div>';
    }

    private function adopted(): \ReflectionProperty {
        $adopted = new \ReflectionProperty( ExpressPay::class, 'adopted' );
        $adopted->setAccessible( true );

        return $adopted;
    }

    /** Hooks a stand-in for FunnelKit's inline buttons where FunnelKit hooks them, and registers it for adoption. */
    private function hook_wallet(): void {
        add_action( self::BILLING_HOOK, [ $this, 'print_wallet' ], 5 );
        ExpressPay::adopt( self::BILLING_HOOK, self::class, 'print_wallet' );
    }
}
