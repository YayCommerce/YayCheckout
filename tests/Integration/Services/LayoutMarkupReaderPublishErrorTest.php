<?php
namespace YayCheckout\Tests\Integration\Services;

use WP_UnitTestCase;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Tests\Support\LayoutFactory;

/** A layout that cannot take an order is refused before it reaches shoppers. */
class LayoutMarkupReaderPublishErrorTest extends WP_UnitTestCase {

    private const COUNTRY      = '<!-- wp:yaycheckout/field {"fieldKey":"billing_country","type":"country"} /-->';
    private const PLACE_ORDER  = '<!-- wp:yaycheckout/place-order /-->';
    private const ORDER_REVIEW = '<!-- wp:yaycheckout/order-review /-->';
    private const PAYMENT      = '<!-- wp:yaycheckout/payment /-->';

    public function test_layouts_that_take_an_order_publish(): void {
        $this->assertSame( '', LayoutMarkupReader::publish_error( LayoutRepository::default_markup() ) );
        $this->assertSame( '', LayoutMarkupReader::publish_error( LayoutFactory::checkout_markup() ) );
        $this->assertSame( '', LayoutMarkupReader::publish_error( LayoutFactory::checkout_markup( self::COUNTRY . self::ORDER_REVIEW . self::PLACE_ORDER ) ) );
    }

    /**
     * @dataProvider unpublishable_layouts
     */
    public function test_refuses_a_layout_that_cannot_take_an_order( string $inner, string $message ): void {
        $this->assertStringContainsString( $message, LayoutMarkupReader::publish_error( LayoutFactory::checkout_markup( $inner ) ) );
    }

    public function unpublishable_layouts(): array {
        return [
            'order review with payment'         => [ self::COUNTRY . self::ORDER_REVIEW . self::PAYMENT, 'Order review already shows' ],
            'no submit button'                  => [ self::COUNTRY . self::PAYMENT, 'Add a Place order block' ],
            'billing_email field beside email'  => [ '<!-- wp:yaycheckout/email /-->' . self::COUNTRY . '<!-- wp:yaycheckout/field {"fieldKey":"billing_email"} /-->' . self::PLACE_ORDER, 'Used more than once: billing_email' ],
            'no country field'                  => [ self::PLACE_ORDER, 'Add a Country / Region field' ],
            'extra field key woocommerce reads' => [ self::COUNTRY . self::PLACE_ORDER . '<!-- wp:yaycheckout/extra-field {"fieldKey":"payment_method"} /-->', 'must not: payment_method' ],
            'extra field key in a woo fieldset' => [ self::COUNTRY . self::PLACE_ORDER . '<!-- wp:yaycheckout/extra-field {"fieldKey":"billing_vat"} /-->', 'must not: billing_vat' ],
        ];
    }
}
