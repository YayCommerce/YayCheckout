<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Support\CompatibilityCatalogue;

/** The normalize() gate sits between the remote JSON and the SPA: anything off-contract is dropped, never passed through. */
class CompatibilityCatalogueTest extends WP_UnitTestCase {

    public function test_normalize_rejects_documents_off_contract(): void {
        $this->assertNull( CompatibilityCatalogue::normalize( 'nope' ) );
        $this->assertNull(
            CompatibilityCatalogue::normalize(
                [
                    'version'  => 2,
                    'gateways' => [],
                    'plugins'  => [],
                ]
            )
        );
        $this->assertNull(
            CompatibilityCatalogue::normalize(
                [
                    'version' => 1,
                    'plugins' => [],
                ]
            )
        );
        $this->assertNull(
            CompatibilityCatalogue::normalize(
                [
                    'version'  => 1,
                    'gateways' => 'x',
                    'plugins'  => [],
                ]
            )
        );
    }

    public function test_normalize_keeps_known_fields_and_drops_the_rest(): void {
        $normalized = CompatibilityCatalogue::normalize(
            [
                'version'  => 1,
                'gateways' => [
                    'stripe'     => [
                        'title'       => 'Stripe',
                        'status'      => 'verified',
                        'express_pay' => 'Apple Pay',
                        'notes'       => [ 'not', 'a', 'string' ],
                        'docs_url'    => 'https://example.com/stripe',
                        'foo'         => 'bar',
                    ],
                    'paypal'     => [
                        'status'   => 'verified',
                        'docs_url' => 'javascript:alert(1)',
                    ],
                    'mollie'     => [
                        'status'   => 'verified',
                        'docs_url' => 'not a url',
                    ],
                    'klarna'     => [ 'status' => 'tested' ],
                    'Stripe Inc' => [ 'status' => 'verified' ],
                ],
                'plugins'  => [
                    'yayboost' => [
                        'status' => 'verified',
                        'notes'  => 'Tested.',
                    ],
                    'other'    => [ 'status' => 'block-only' ],
                ],
            ]
        );

        $this->assertSame(
            [
                'gateways' => [
                    'stripe' => [
                        'title'       => 'Stripe',
                        'status'      => 'verified',
                        'express_pay' => 'Apple Pay',
                        'notes'       => null,
                        'docs_url'    => 'https://example.com/stripe',
                    ],
                    'paypal' => [
                        'title'       => null,
                        'status'      => 'verified',
                        'express_pay' => null,
                        'notes'       => null,
                        'docs_url'    => null,
                    ],
                    'mollie' => [
                        'title'       => null,
                        'status'      => 'verified',
                        'express_pay' => null,
                        'notes'       => null,
                        'docs_url'    => null,
                    ],
                ],
                'plugins'  => [
                    'yayboost' => [
                        'title'       => null,
                        'status'      => 'verified',
                        'express_pay' => null,
                        'notes'       => 'Tested.',
                        'docs_url'    => null,
                    ],
                ],
            ],
            $normalized
        );
    }

    public function test_bundled_list_obeys_its_own_contract(): void {
        $bundled = CompatibilityCatalogue::bundled();

        $this->assertSame( $bundled, CompatibilityCatalogue::normalize( [ 'version' => 1 ] + $bundled ) );
        $this->assertSame( CompatibilityCatalogue::STATUS_VERIFIED, $bundled['gateways']['cod']['status'] );
        $this->assertSame( CompatibilityCatalogue::STATUS_COMMUNITY, $bundled['gateways']['stripe']['status'] );
        $this->assertNull( $bundled['gateways']['stripe']['express_pay'] );
        $this->assertSame( CompatibilityCatalogue::STATUS_VERIFIED, $bundled['plugins']['yayboost']['status'] );
    }
}
