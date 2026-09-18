<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Tests\Support\LayoutFactory;

/** A scheduled layout reaching its date through cron ends up with a snapshot. */
class CronPublishTest extends WP_UnitTestCase {

    public function test_cron_publish_writes_the_snapshot_once(): void {
        $id = LayoutFactory::create_scheduled();
        LayoutFactory::backdate( $id );
        $marker_writes = LayoutFactory::count_publish_marker_writes();

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- firing core's cron event, not defining a hook.
        do_action( 'publish_future_post', $id );

        $this->assertSame( 'publish', get_post( $id )->post_status );
        $this->assertSame( LayoutFactory::checkout_markup(), get_post_meta( $id, Layout::META_PUBLISHED, true ) );
        $this->assertSame( 1, $marker_writes() );
        $this->assertCount( 1, wp_get_post_revisions( $id ) );
    }
}
