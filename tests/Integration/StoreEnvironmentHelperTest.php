<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Helpers\StoreEnvironmentHelper;
use YayCheckout\Models\Settings;
use YayCheckout\Repositories\SettingsRepository;

/** Pins the wire shape of the Store section and that each value is read live from this install. */
class StoreEnvironmentHelperTest extends WP_UnitTestCase {

    public function test_describe_reports_the_install(): void {
        $store = StoreEnvironmentHelper::describe();

        $this->assertSame( [ 'store_type', 'woocommerce_version', 'wordpress_version', 'theme', 'safe_mode' ], array_keys( $store ) );
        $this->assertContains( $store['store_type'], [ 'classic', 'block' ] );
        $this->assertSame( WC()->version, $store['woocommerce_version'] );
        $this->assertNotSame( '', $store['wordpress_version'] );
        $this->assertSame( [ 'name', 'version', 'is_block_theme' ], array_keys( $store['theme'] ) );
        $this->assertNotSame( '', $store['theme']['name'] );
        $this->assertIsBool( $store['theme']['is_block_theme'] );
        $this->assertFalse( $store['safe_mode'] );
    }

    /** The test library deletes every post after install, so the checkout page WC_Install created is gone; make one. */
    public function test_store_type_follows_the_checkout_page_content(): void {
        $checkout_page = self::factory()->post->create(
            [
                'post_type'    => 'page',
                'post_content' => '<!-- wp:woocommerce/checkout /-->',
            ]
        );
        update_option( 'woocommerce_checkout_page_id', $checkout_page );

        $this->assertSame( 'block', StoreEnvironmentHelper::describe()['store_type'] );

        wp_update_post(
            [
                'ID'           => $checkout_page,
                'post_content' => '[woocommerce_checkout]',
            ]
        );
        $this->assertSame( 'classic', StoreEnvironmentHelper::describe()['store_type'] );
    }

    public function test_safe_mode_reads_the_saved_setting(): void {
        ( new SettingsRepository() )->save( Settings::sanitize( [ 'general' => [ 'safe_mode' => true ] ] ) );

        $this->assertTrue( StoreEnvironmentHelper::describe()['safe_mode'] );
    }
}
