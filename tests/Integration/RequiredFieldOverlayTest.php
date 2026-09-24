<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Services\RequiredFieldOverlay;

/**
 * WooCommerce's checkout field registry after the layout's required flags
 * are laid over it. Layout maps are shaped like LayoutMarkupReader output.
 */
class RequiredFieldOverlayTest extends WP_UnitTestCase {

    /** The layout decides required; a field it leaves out is optional; email is always required; non-address fieldsets are untouched. */
    public function test_layout_decides_required(): void {
        $fields = [
            'billing'  => [
                'billing_first_name' => [
                    'type'     => 'text',
                    'required' => true,
                ],
                'billing_company'    => [
                    'type'     => 'text',
                    'required' => true,
                ],
                'billing_email'      => [
                    'type'     => 'email',
                    'required' => false,
                ],
            ],
            'shipping' => [
                'shipping_city' => [
                    'type'     => 'text',
                    'required' => true,
                ],
            ],
            'order'    => [
                'order_comments' => [
                    'type'     => 'textarea',
                    'required' => true,
                ],
            ],
        ];
        $layout = [
            'billing_first_name' => [
                'fieldKey' => 'billing_first_name',
                'required' => false,
            ],
            'billing_email'      => [
                'fieldKey' => 'billing_email',
                'type'     => 'email',
                'required' => false,
            ],
        ];

        $result = RequiredFieldOverlay::apply( $fields, $layout );

        $this->assertFalse( $result['billing']['billing_first_name']['required'] );
        $this->assertFalse( $result['billing']['billing_company']['required'] );
        $this->assertTrue( $result['billing']['billing_email']['required'] );
        $this->assertFalse( $result['shipping']['shipping_city']['required'] );
        $this->assertSame( $fields['order'], $result['order'] );
    }

    /** Email stays required even when the layout has no Contact block. */
    public function test_email_required_without_layout_entry(): void {
        $result = RequiredFieldOverlay::apply(
            [
                'billing' => [
                    'billing_email' => [
                        'type'     => 'email',
                        'required' => false,
                    ],
                ],
            ],
            []
        );

        $this->assertTrue( $result['billing']['billing_email']['required'] );
    }

    /**
     * A layout field WooCommerce removed (Customizer "Hidden") is added back to
     * its own fieldset with derived validate rules; a layout-supplied validate
     * wins; a missing fieldset starts empty.
     */
    public function test_restores_fields_woocommerce_removed(): void {
        $layout = [
            'billing_email'    => [
                'fieldKey' => 'billing_email',
                'type'     => 'email',
                'label'    => 'Email',
                'required' => true,
            ],
            'billing_phone'    => [
                'fieldKey'     => 'billing_phone',
                'type'         => 'tel',
                'label'        => 'Phone',
                'required'     => true,
                'placeholder'  => '',
                'autocomplete' => 'tel',
            ],
            'billing_postcode' => [
                'fieldKey' => 'billing_postcode',
                'required' => false,
                'validate' => [ 'custom' ],
            ],
            'shipping_state'   => [
                'fieldKey'    => 'shipping_state',
                'type'        => 'state',
                'label'       => 'State',
                'required'    => false,
                'placeholder' => 'Pick',
            ],
        ];

        $result = RequiredFieldOverlay::apply( [ 'billing' => [] ], $layout );

        $this->assertSame(
            [
                'billing'  => [
                    'billing_email'    => [
                        'type'     => 'email',
                        'label'    => 'Email',
                        'required' => true,
                        'validate' => [ 'email' ],
                    ],
                    'billing_phone'    => [
                        'type'         => 'tel',
                        'label'        => 'Phone',
                        'required'     => true,
                        'validate'     => [ 'phone' ],
                        'autocomplete' => 'tel',
                    ],
                    'billing_postcode' => [
                        'type'     => 'text',
                        'label'    => 'billing_postcode',
                        'required' => false,
                        'validate' => [ 'custom' ],
                    ],
                ],
                'shipping' => [
                    'shipping_state' => [
                        'type'        => 'state',
                        'label'       => 'State',
                        'required'    => false,
                        'validate'    => [ 'state' ],
                        'placeholder' => 'Pick',
                    ],
                ],
            ],
            $result
        );
    }
}
