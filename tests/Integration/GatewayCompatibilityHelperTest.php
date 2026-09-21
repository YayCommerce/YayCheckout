<?php
namespace YayCheckout\Tests\Integration;

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WP_UnitTestCase;
use YayCheckout\Helpers\GatewayCompatibilityHelper;
use YayCheckout\Support\CompatibilityCatalogue;
use YayCheckout\Tests\Support\FakeBlockPaymentMethod;
use YayCheckout\Tests\Support\FakeClassicGateway;

/** Pins the gateway rows: enabled classic gateways in WooCommerce's order, then block-only methods, catalogue status winning over detection. */
class GatewayCompatibilityHelperTest extends WP_UnitTestCase {

    private PaymentMethodRegistry $registry;

    public function set_up(): void {
        parent::set_up();
        add_filter( 'woocommerce_payment_gateways', [ $this, 'add_fake_gateway' ] );
        WC()->payment_gateways()->init();

        $this->registry = Package::container()->get( PaymentMethodRegistry::class );
        $this->registry->register( new FakeBlockPaymentMethod() );
    }

    public function tear_down(): void {
        foreach ( [ 'fake_block_only', 'fake_classic', 'cod', 'fake_broken' ] as $name ) {
            if ( $this->registry->is_registered( $name ) ) {
                $this->registry->unregister( $name );
            }
        }
        remove_filter( 'woocommerce_payment_gateways', [ $this, 'add_fake_gateway' ] );
        // init() overwrites slots in place and never empties the list, so the fake would survive into later test classes.
        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();
        parent::tear_down();
    }

    public function add_fake_gateway( array $gateways ): array {
        $gateways[] = new FakeClassicGateway();

        return $gateways;
    }

    private function row( array $rows, string $id ): ?array {
        foreach ( $rows as $row ) {
            if ( $id === $row['id'] ) {
                return $row;
            }
        }

        return null;
    }

    public function test_detection_without_a_catalogue(): void {
        $rows = GatewayCompatibilityHelper::list( [] );
        $ids  = array_column( $rows, 'id' );

        $this->assertNotContains( 'cod', $ids );
        $this->assertSame(
            [
                'id'          => 'fake_classic',
                'title'       => 'Fake Classic',
                'status'      => CompatibilityCatalogue::STATUS_UNVERIFIED,
                'express_pay' => null,
                'notes'       => null,
                'docs_url'    => null,
            ],
            $this->row( $rows, 'fake_classic' )
        );
        $this->assertSame(
            [
                'id'          => 'fake_block_only',
                'title'       => 'Fake Block Only',
                'status'      => CompatibilityCatalogue::STATUS_BLOCK_ONLY,
                'express_pay' => null,
                'notes'       => null,
                'docs_url'    => null,
            ],
            $this->row( $rows, 'fake_block_only' )
        );
        $this->assertSame( count( $ids ) - 1, array_search( 'fake_block_only', $ids, true ) );
    }

    public function test_catalogue_entry_fills_the_row(): void {
        $rows = GatewayCompatibilityHelper::list(
            [
                'gateways' => [
                    'fake_classic'    => [
                        'title'       => 'Fake Classic Pro',
                        'status'      => CompatibilityCatalogue::STATUS_VERIFIED,
                        'express_pay' => 'Apple Pay',
                        'notes'       => 'Tested.',
                        'docs_url'    => 'https://example.com/fake',
                    ],
                    'fake_block_only' => [
                        'title'       => 'Fake Block',
                        'status'      => CompatibilityCatalogue::STATUS_BLOCK_ONLY,
                        'express_pay' => null,
                        'notes'       => 'Block only.',
                        'docs_url'    => null,
                    ],
                ],
            ]
        );

        $classic = $this->row( $rows, 'fake_classic' );
        $this->assertSame( 'Fake Classic', $classic['title'] );
        $this->assertSame( CompatibilityCatalogue::STATUS_VERIFIED, $classic['status'] );
        $this->assertSame( 'Apple Pay', $classic['express_pay'] );
        $this->assertSame( 'Tested.', $classic['notes'] );
        $this->assertSame( 'https://example.com/fake', $classic['docs_url'] );

        $block = $this->row( $rows, 'fake_block_only' );
        $this->assertSame( 'Fake Block', $block['title'] );
        $this->assertSame( 'Block only.', $block['notes'] );
    }

    public function test_catalogue_status_overrides_detection(): void {
        $rows = GatewayCompatibilityHelper::list(
            [ 'gateways' => [ 'fake_classic' => [ 'status' => CompatibilityCatalogue::STATUS_BLOCK_ONLY ] ] ]
        );

        $this->assertSame( CompatibilityCatalogue::STATUS_BLOCK_ONLY, $this->row( $rows, 'fake_classic' )['status'] );
    }

    public function test_a_disabled_classic_gateway_with_a_block_integration_is_not_block_only(): void {
        $this->registry->register( new FakeBlockPaymentMethod( 'cod' ) );

        $this->assertNotContains( 'cod', array_column( GatewayCompatibilityHelper::list( [] ), 'id' ) );
    }

    public function test_an_integration_whose_is_active_throws_is_skipped(): void {
        $this->registry->register( new FakeBlockPaymentMethod( 'fake_broken', true ) );

        $ids = array_column( GatewayCompatibilityHelper::list( [] ), 'id' );

        $this->assertNotContains( 'fake_broken', $ids );
        $this->assertContains( 'fake_block_only', $ids );
    }

    public function test_a_gateway_registered_on_both_sides_is_one_row(): void {
        $this->registry->register( new FakeBlockPaymentMethod( 'fake_classic' ) );

        $ids = array_column( GatewayCompatibilityHelper::list( [] ), 'id' );

        $this->assertSame( 1, count( array_keys( $ids, 'fake_classic', true ) ) );
        $this->assertSame( CompatibilityCatalogue::STATUS_UNVERIFIED, $this->row( GatewayCompatibilityHelper::list( [] ), 'fake_classic' )['status'] );
    }
}
