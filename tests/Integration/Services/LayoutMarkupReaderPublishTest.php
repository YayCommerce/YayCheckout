<?php
namespace YayCheckout\Tests\Integration\Services;

use WP_UnitTestCase;
use YayCheckout\Services\LayoutMarkupReader;

/** A Free install refuses to publish a layout that still holds Pro blocks, such as Steps after a downgrade. */
class LayoutMarkupReaderPublishTest extends WP_UnitTestCase {

    private const MARKUP = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/steps --><!-- wp:yaycheckout/step /--><!-- /wp:yaycheckout/steps --><!-- /wp:yaycheckout/checkout -->';

    public function test_refuses_a_yaycheckout_block_that_is_not_registered(): void {
        $error = LayoutMarkupReader::publish_error( self::MARKUP, [ 'yaycheckout/checkout', 'yaycheckout/step' ] );

        $this->assertStringContainsString( 'YayCheckout Pro', $error );
    }

    public function test_publishes_when_every_yaycheckout_block_is_registered(): void {
        $error = LayoutMarkupReader::publish_error( self::MARKUP, [ 'yaycheckout/checkout', 'yaycheckout/steps', 'yaycheckout/step' ] );

        $this->assertSame( '', $error );
    }
}
