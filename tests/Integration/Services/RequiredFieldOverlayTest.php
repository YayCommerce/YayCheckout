<?php
namespace YayCheckout\Tests\Integration\Services;

use WP_UnitTestCase;
use YayCheckout\Services\RequiredFieldOverlay;

/** The layout decides required flags, except where WooCommerce's locale hides the field. */
class RequiredFieldOverlayTest extends WP_UnitTestCase {

    private function fields(): array {
        return [
            'billing'  => [
                'billing_postcode' => [ 'required' => false ],
                'billing_state'    => [ 'required' => false ],
                'billing_city'     => [ 'required' => true ],
                'billing_email'    => [
                    'required' => false,
                    'type'     => 'email',
                ],
            ],
            'shipping' => [ 'shipping_state' => [ 'required' => false ] ],
        ];
    }

    private function layout(): array {
        return [
            'billing_postcode' => [ 'required' => true ],
            'billing_state'    => [ 'required' => true ],
            'billing_city'     => [ 'required' => false ],
            'shipping_state'   => [ 'required' => true ],
        ];
    }

    public function test_a_locale_hidden_field_stays_optional(): void {
        $locales = WC()->countries->get_country_locale();
        $fields  = RequiredFieldOverlay::apply(
            $this->fields(),
            $this->layout(),
            [
                'billing'  => $locales['AE'],
                'shipping' => $locales['SG'],
            ]
        );

        $this->assertFalse( $fields['billing']['billing_postcode']['required'] );
        $this->assertFalse( $fields['shipping']['shipping_state']['required'] );
    }

    public function test_a_shown_field_follows_the_layout(): void {
        $fields = RequiredFieldOverlay::apply( $this->fields(), $this->layout(), [ 'billing' => WC()->countries->get_country_locale()['AE'] ] );

        $this->assertTrue( $fields['billing']['billing_state']['required'] );
        $this->assertFalse( $fields['billing']['billing_city']['required'] );
    }

    public function test_billing_email_is_always_required(): void {
        $fields = RequiredFieldOverlay::apply( $this->fields(), [] );

        $this->assertTrue( $fields['billing']['billing_email']['required'] );
    }
}
