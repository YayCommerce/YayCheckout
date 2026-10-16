<?php
namespace YayCheckout\Tests\Integration\Services;

use WP_UnitTestCase;
use YayCheckout\Services\LayoutMarkupReader;

/** A Free install refuses to publish a layout that still holds Pro blocks, such as Steps after a downgrade. */
class LayoutMarkupReaderPublishTest extends WP_UnitTestCase {

    private const MARKUP = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/steps --><!-- wp:yaycheckout/step /--><!-- /wp:yaycheckout/steps --><!-- wp:yaycheckout/from-a-newer-version /--><!-- /wp:yaycheckout/checkout -->';

    public function test_refuses_a_locked_pro_block(): void {
        $error = LayoutMarkupReader::publish_error( self::MARKUP, [ 'yaycheckout/steps', 'yaycheckout/step' ] );

        $this->assertStringContainsString( 'YayCheckout Pro', $error );
    }

    public function test_publishes_when_no_pro_block_is_locked(): void {
        // An unknown YayCheckout block, say from a newer version's export, is not a Pro block.
        $error = LayoutMarkupReader::publish_error( self::MARKUP, [] );

        $this->assertSame( '', $error );
    }
}
