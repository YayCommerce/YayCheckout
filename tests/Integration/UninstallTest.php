<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Models\Settings;
use YayCheckout\Support\CompatibilityCatalogue;

/** The uninstall script removes every layout and yaycheckout_* row only when the merchant opted in, and nothing else. */
class UninstallTest extends WP_UnitTestCase {

    /** @var int[] Layouts in each status the merchant can leave one in, the first one active. */
    private $layout_ids = [];

    /** @var int */
    private $other_post_id = 0;

    public function set_up(): void {
        parent::set_up();

        foreach ( [ 'publish', 'draft', 'trash' ] as $status ) {
            $id = self::factory()->post->create(
                [
                    'post_type'   => Layout::POST_TYPE,
                    'post_status' => $status,
                ]
            );
            update_post_meta( $id, Layout::META_DESIGN, 'design' );
            $this->layout_ids[] = $id;
        }
        $this->other_post_id = self::factory()->post->create();

        // The plugin is loaded here, as under wp plugin uninstall --deactivate, so its active-layout guard runs.
        update_option( Layout::OPTION_ACTIVE, [ $this->layout_ids[0] ] );
        update_option( Settings::OPTION_SETUP_DONE, true );
        update_option( 'yaycheckoutnot_ours', 'keep' );
        update_option( 'woocommerce_uninstall_test', 'keep' );
        set_transient( CompatibilityCatalogue::TRANSIENT, [ 'source' => 'bundled' ], HOUR_IN_SECONDS );
    }

    public function test_opted_in_deletes_layouts_options_and_transients(): void {
        global $wpdb;
        update_option( Settings::OPTION_SETTINGS, [ 'general' => [ 'delete_data_on_uninstall' => true ] ] );

        $this->uninstall();

        foreach ( $this->layout_ids as $id ) {
            $this->assertNull( get_post( $id ), "layout {$id}" );
            $this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
        $this->assertNotNull( get_post( $this->other_post_id ) );

        $gone = [
            Settings::OPTION_SETTINGS,
            Settings::OPTION_SETUP_DONE,
            Layout::OPTION_ACTIVE,
            '_transient_' . CompatibilityCatalogue::TRANSIENT,
            '_transient_timeout_' . CompatibilityCatalogue::TRANSIENT,
        ];
        foreach ( $gone as $name ) {
            $this->assertFalse( $this->option_exists( $name ), $name );
        }
        $this->assertTrue( $this->option_exists( 'yaycheckoutnot_ours' ) );
        $this->assertTrue( $this->option_exists( 'woocommerce_uninstall_test' ) );
    }

    public function test_not_opted_in_keeps_everything(): void {
        update_option( Settings::OPTION_SETTINGS, [ 'general' => [ 'delete_data_on_uninstall' => false ] ] );

        $this->uninstall();

        $this->assert_all_kept();
    }

    public function test_never_saved_settings_keep_everything(): void {
        delete_option( Settings::OPTION_SETTINGS );

        $this->uninstall();

        $this->assert_all_kept();
    }

    private function assert_all_kept(): void {
        foreach ( $this->layout_ids as $id ) {
            $this->assertNotNull( get_post( $id ), "layout {$id}" );
        }
        $this->assertTrue( $this->option_exists( Layout::OPTION_ACTIVE ) );
        $this->assertTrue( $this->option_exists( '_transient_' . CompatibilityCatalogue::TRANSIENT ) );
    }

    private function uninstall(): void {
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            define( 'WP_UNINSTALL_PLUGIN', 'yaycheckout/yaycheckout.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WordPress's own constant.
        }
        include YAYCHECKOUT_PLUGIN_DIR . 'uninstall.php';
    }

    /** Reads the table, not get_option(), so a stale cache cannot hide a leftover row. */
    private function option_exists( string $name ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
}
