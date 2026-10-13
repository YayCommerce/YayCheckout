<?php
namespace YayCheckout\Tests\Integration\Services;

use YayCheckout\Services\LayoutMarkupReader;

/** The publish rules that keep a layout from going live without a way to pay. */
class LayoutMarkupReaderPublishErrorTest extends \WP_UnitTestCase {

    private const PAY   = '<!-- wp:yaycheckout/payment /-->';
    private const ORDER = '<!-- wp:yaycheckout/place-order /-->';

    private static function checkout( string $inner ): string {
        return '<!-- wp:yaycheckout/checkout -->' . $inner . '<!-- /wp:yaycheckout/checkout -->';
    }

    public function test_missing_place_order_is_refused(): void {
        $this->assertSame( 'Add a Place order block to this layout before activating it.', LayoutMarkupReader::publish_error( self::checkout( self::PAY ) ) );
    }

    public function test_missing_payment_is_refused(): void {
        $this->assertSame( 'Add a Payment block to this layout before activating it.', LayoutMarkupReader::publish_error( self::checkout( self::ORDER ) ) );
    }

    public function test_checkout_rule_still_comes_first(): void {
        $this->assertSame( 'Add a Checkout block to this layout before activating it.', LayoutMarkupReader::publish_error( self::PAY . self::ORDER ) );
    }

    public function test_complete_layout_passes(): void {
        $this->assertSame( '', LayoutMarkupReader::publish_error( self::checkout( self::PAY . self::ORDER ) ) );
    }
}
