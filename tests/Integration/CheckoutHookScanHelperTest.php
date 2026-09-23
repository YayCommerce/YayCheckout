<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Helpers\CheckoutHookScanHelper;
use YayCheckout\Support\CompatibilityCatalogue;

/** Scans fixture roots instead of the live install; WP_UnitTestCase restores $wp_filter after each test, so fixture hooks never leak. */
class CheckoutHookScanHelperTest extends WP_UnitTestCase {

    private const FIXTURES = __DIR__ . '/../fixtures';

    private CheckoutHookScanHelper $helper;

    public function set_up(): void {
        parent::set_up();

        require_once self::FIXTURES . '/plugins/fake-checkout-plugin/fake-checkout-plugin.php';
        require_once self::FIXTURES . '/mu-plugins/fake-mu.php';
        require_once self::FIXTURES . '/themes/fake-theme/functions.php';
        require_once self::FIXTURES . '/plugins/Fake-Cased/fake-cased.php';
        fake_checkout_plugin_register();
        fake_mu_register();
        fake_theme_register();
        fake_cased_register();

        $this->helper = new CheckoutHookScanHelper(
            self::FIXTURES . '/plugins',
            self::FIXTURES . '/mu-plugins',
            self::FIXTURES . '/themes'
        );
    }

    public function test_detects_plugin_mu_plugin_and_theme_sorted_by_name(): void {
        $rows = $this->helper->scan( [] );

        $this->assertSame( [ 'Fake Cased', 'Fake Checkout Plugin', 'Fake Mu', 'Fake Theme' ], array_column( $rows, 'name' ) );
        $this->assertSame( 'fake-cased', $rows[0]['slug'] );
        array_shift( $rows );

        $this->assertSame(
            [
                'slug'     => 'fake-checkout-plugin',
                'name'     => 'Fake Checkout Plugin',
                'version'  => '3.2.0',
                'kind'     => 'plugin',
                'touches'  => [ 'fields', 'templates', 'gateways' ],
                'status'   => CompatibilityCatalogue::STATUS_UNVERIFIED,
                'notes'    => 'Hooks into checkout fields, templates, gateways. Not on our tested list.',
                'docs_url' => null,
            ],
            $rows[0]
        );

        $this->assertSame( 'mu-plugin', $rows[1]['kind'] );
        $this->assertSame( 'fake-mu', $rows[1]['slug'] );
        $this->assertSame( [ 'submission' ], $rows[1]['touches'] );

        $this->assertSame( 'theme', $rows[2]['kind'] );
        $this->assertSame( 'fake-theme', $rows[2]['slug'] );
        $this->assertSame( '1.0', $rows[2]['version'] );
        $this->assertSame( [ 'fields' ], $rows[2]['touches'] );
    }

    public function test_woocommerce_and_yaycheckout_are_excluded_from_a_live_scan(): void {
        add_filter(
            'woocommerce_checkout_fields',
            static function ( $fields ) {
                return $fields;
            }
        );
        $this->assertNotEmpty( $GLOBALS['wp_filter']['woocommerce_checkout_fields']->callbacks );

        $slugs = array_column( ( new CheckoutHookScanHelper() )->scan( [] ), 'slug' );

        $this->assertNotContains( 'woocommerce', $slugs );
        $this->assertNotContains( 'yaycheckout', $slugs );
    }

    public function test_catalogue_entry_fills_the_row(): void {
        $rows = $this->helper->scan(
            [
                'plugins' => [
                    'fake-checkout-plugin' => [
                        'status'   => CompatibilityCatalogue::STATUS_VERIFIED,
                        'notes'    => 'Tested.',
                        'docs_url' => 'https://example.com/x',
                    ],
                ],
            ]
        );

        $this->assertSame( CompatibilityCatalogue::STATUS_VERIFIED, $rows[1]['status'] );
        $this->assertSame( 'Tested.', $rows[1]['notes'] );
        $this->assertSame( 'https://example.com/x', $rows[1]['docs_url'] );
        $this->assertSame( CompatibilityCatalogue::STATUS_UNVERIFIED, $rows[0]['status'] );
    }

    public function test_unreflectable_callbacks_are_skipped(): void {
        add_filter( 'woocommerce_checkout_fields', 'this_function_does_not_exist' );
        add_filter( 'woocommerce_checkout_fields', 'Missing_Class::method' );

        $this->assertCount( 4, $this->helper->scan( [] ) );
    }
}
