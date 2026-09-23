<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Services\LayoutMarkupReader;

/**
 * Pins what a layout's markup reads as: one layout exercising every block,
 * nested in a group, with loose HTML, keyless blocks and a duplicate Key.
 */
class LayoutMarkupReaderTest extends WP_UnitTestCase {

    private const FIXTURE = "\n<p>loose html</p>\n"
        . '<!-- wp:group --><div class="wp-block-group">'
        . '<!-- wp:yaycheckout/checkout -->'
        . '<!-- wp:yaycheckout/contact {"label":"Email address"} /-->'
        . '<!-- wp:yaycheckout/billing -->'
        . '<!-- wp:yaycheckout/field {"fieldKey":"billing_first_name","label":"First name","required":true,"autocomplete":"given-name","validate":["name"],"width":"half"} /-->'
        . '<!-- wp:yaycheckout/field {"fieldKey":"","label":"No key"} /-->'
        . '<!-- /wp:yaycheckout/billing -->'
        . '<!-- wp:yaycheckout/shipping /-->'
        . '<!-- wp:paragraph --><p>Note</p><!-- /wp:paragraph -->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"gift","label":"Gift wrap","type":"select","required":true,"options":[{"label":"Yes","value":"yes"},{"label":"","value":""},{"label":"No","value":"no"},{"value":"maybe"}]} /-->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"  ","label":"Empty key"} /-->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"vat","label":"VAT","placeholder":"EU VAT"} /-->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"vat","label":"Tax ID","required":true} /-->'
        . '<!-- wp:yaycheckout/slot {"slotName":"Promo Box"} /-->'
        . '<!-- wp:yaycheckout/slot {"slotName":""} /-->'
        . '<!-- wp:yaycheckout/order-review /-->'
        . '<!-- wp:yaycheckout/shipping-methods /-->'
        . '<!-- wp:yaycheckout/express-pay /-->'
        . '<!-- wp:yaycheckout/payment /-->'
        . '<!-- wp:yaycheckout/order-summary /-->'
        . '<!-- wp:yaycheckout/place-order /-->'
        . '<!-- /wp:yaycheckout/checkout -->'
        . '</div><!-- /wp:group -->' . "\n";

    /** Sections in document order; a slot block adds its sanitize_key()'d name, an empty one adds nothing. */
    private const SLOTS = [
        'contact',
        'billing',
        'shipping',
        'promobox',
        'order_review',
        'shipping_methods',
        'express_pay',
        'payment',
        'order_summary',
        'place_order',
    ];

    /** Contact contributes billing_email, required unless turned off; a field without a Key is skipped. */
    private const FIELDS = [
        'billing_email'      => [
            'fieldKey'     => 'billing_email',
            'type'         => 'email',
            'label'        => 'Email address',
            'placeholder'  => '',
            'description'  => '',
            'required'     => true,
            'enabled'      => true,
            'autocomplete' => '',
            'validate'     => [],
            'width'        => 'full',
        ],
        'billing_first_name' => [
            'fieldKey'     => 'billing_first_name',
            'type'         => 'text',
            'label'        => 'First name',
            'placeholder'  => '',
            'description'  => '',
            'required'     => true,
            'enabled'      => true,
            'autocomplete' => 'given-name',
            'validate'     => [ 'name' ],
            'width'        => 'half',
        ],
    ];

    /**
     * Blank options drop, a label-less option shows its value, a blank Key is
     * skipped, and a later duplicate Key replaces the earlier field whole.
     */
    private const EXTRA_FIELDS = [
        'gift' => [
            'fieldKey'    => 'gift',
            'type'        => 'select',
            'label'       => 'Gift wrap',
            'placeholder' => '',
            'required'    => true,
            'options'     => [
                'yes'   => 'Yes',
                'no'    => 'No',
                'maybe' => 'maybe',
            ],
        ],
        'vat'  => [
            'fieldKey'    => 'vat',
            'type'        => 'text',
            'label'       => 'Tax ID',
            'placeholder' => '',
            'required'    => true,
            'options'     => [],
        ],
    ];

    public function test_slots(): void {
        $this->assertSame( self::SLOTS, LayoutMarkupReader::slots( self::FIXTURE ) );
    }

    public function test_fields(): void {
        $this->assertSame( self::FIELDS, LayoutMarkupReader::fields( self::FIXTURE ) );
    }

    public function test_extra_fields(): void {
        $this->assertSame( self::EXTRA_FIELDS, LayoutMarkupReader::extra_fields( self::FIXTURE ) );
    }

    public function test_order_modes_is_empty(): void {
        $this->assertSame( [], LayoutMarkupReader::order_modes( self::FIXTURE ) );
    }

    /** Publish owns the Checkout rule; reading a layout without one just finds nothing. */
    public function test_reads_do_not_throw_without_checkout(): void {
        foreach ( [ '', '<p>x</p>', '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' ] as $markup ) {
            $this->assertSame( [], LayoutMarkupReader::slots( $markup ) );
            $this->assertSame( [], LayoutMarkupReader::fields( $markup ) );
            $this->assertSame( [], LayoutMarkupReader::extra_fields( $markup ) );
        }
    }

    public function test_malformed_attributes_read_as_empty(): void {
        $markup = '<!-- wp:yaycheckout/contact {bad} /--><!-- wp:yaycheckout/field {bad} /--><!-- wp:yaycheckout/extra-field {bad} /-->';

        $this->assertSame( [ 'billing_email' ], array_keys( LayoutMarkupReader::fields( $markup ) ) );
        $this->assertSame( [], LayoutMarkupReader::extra_fields( $markup ) );
    }

    public function test_slot_named_zero_is_kept(): void {
        $this->assertSame( [ '0' ], LayoutMarkupReader::slots( '<!-- wp:yaycheckout/slot {"slotName":"0"} /-->' ) );
    }

    /** A Key outside letters, digits and underscores (or with a leading underscore) is treated as no Key. */
    public function test_malformed_extra_field_keys_are_skipped(): void {
        $markup = '<!-- wp:yaycheckout/extra-field {"fieldKey":"_billing_email"} /--><!-- wp:yaycheckout/extra-field {"fieldKey":"vat number"} /--><!-- wp:yaycheckout/extra-field {"fieldKey":"vat_no"} /-->';

        $this->assertSame( [ 'vat_no' ], array_keys( LayoutMarkupReader::extra_fields( $markup ) ) );
    }
}
