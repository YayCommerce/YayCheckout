<?php
namespace YayCheckout\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Tests\Support\LayoutFactory;

/** Pins the stored rule shape, the normalization on save, and what the rules route refuses. */
class LayoutRulesRestTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
    }

    private function put( int $id, $rules ) {
        $request = new WP_REST_Request( 'PUT', "/yaycheckout/v1/layouts/$id/rules" );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( [ 'rules' => $rules ] ) );

        return rest_do_request( $request );
    }

    private function get( int $id ) {
        return rest_do_request( new WP_REST_Request( 'GET', "/yaycheckout/v1/layouts/$id/rules" ) );
    }

    public function test_saves_normalized_rules_and_reads_them_back(): void {
        $id = LayoutFactory::create_draft();

        $response = $this->put(
            $id,
            [
                [
                    [
						'type'     => 'country',
						'operator' => 'in',
						'value'    => [ 'DE', 'AT', 'DE' ],
					],
                    [
						'type'     => 'cart_total',
						'operator' => 'gt',
						'value'    => 150,
                    ],
                    [
						'type'     => 'device',
						'operator' => 'is',
						'value'    => 'desktop',
                    ],
                ],
                [
                    [
						'type'     => 'product_in_cart',
						'operator' => 'not_in',
						'value'    => [ '289', '301' ],
					],
                ],
            ]
        );

        $expected = [
            [
                [
					'type'     => 'country',
					'operator' => 'in',
					'value'    => [ 'DE', 'AT' ],
				],
                [
					'type'     => 'cart_total',
					'operator' => 'gt',
					'value'    => 150.0,
                ],
                [
					'type'     => 'device',
					'operator' => 'is',
					'value'    => 'desktop',
                ],
            ],
            [
                [
					'type'     => 'product_in_cart',
					'operator' => 'not_in',
					'value'    => [ 289, 301 ],
				],
            ],
        ];

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( $expected, $response->get_data()['rules'] );
        $this->assertSame( $expected, get_post_meta( $id, Layout::META_RULES, true ) );
        $this->assertSame( $expected, $this->get( $id )->get_data()['rules'] );
    }

    public function test_saving_rules_leaves_the_layout_itself_alone(): void {
        $id     = LayoutFactory::create_draft();
        $before = get_post( $id );

        $this->put(
            $id,
            [
				[
					[
						'type'     => 'customer_role',
						'operator' => 'in',
						'value'    => [ 'wholesale' ],
					],
                ],
            ]
        );

        $after = get_post( $id );
        $this->assertSame( $before->post_content, $after->post_content );
        $this->assertSame( $before->post_status, $after->post_status );
        $this->assertSame( $before->post_modified_gmt, $after->post_modified_gmt );
    }

    public function test_an_empty_list_clears_the_rules(): void {
        $id = LayoutFactory::create_draft();
        $this->put(
            $id,
            [
				[
					[
						'type'     => 'payment_method',
						'operator' => 'in',
						'value'    => [ 'cod' ],
					],
				],
            ]
        );

        $response = $this->put( $id, [] );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( [], $response->get_data()['rules'] );
        $this->assertFalse( metadata_exists( 'post', $id, Layout::META_RULES ) );
    }

    /**
     * @dataProvider invalid_rules
     */
    public function test_refuses_invalid_rules_and_keeps_the_stored_ones( $rules ): void {
        $id    = LayoutFactory::create_draft();
        $saved = [
			[
				[
					'type'     => 'device',
					'operator' => 'is',
					'value'    => 'mobile',
                ],
            ],
        ];
        $this->put( $id, $saved );

        $response = $this->put( $id, $rules );

        $this->assertSame( 400, $response->get_status() );
        $this->assertSame( $saved, get_post_meta( $id, Layout::META_RULES, true ) );
    }

    public function invalid_rules(): array {
        return [
            'not a list'          => [ [ 'a' => [] ] ],
            'empty group'         => [ [ [] ] ],
            'unknown type'        => [
				[
					[
						[
							'type'     => 'weather',
							'operator' => 'is',
							'value'    => 'sunny',
						],
					],
                ],
            ],
            'operator not for it' => [
				[
					[
						[
							'type'     => 'country',
							'operator' => 'gt',
							'value'    => [ 'DE' ],
						],
                    ],
                ],
            ],
            'empty list value'    => [
				[
					[
						[
							'type'     => 'country',
							'operator' => 'in',
							'value'    => [],
						],
                    ],
                ],
            ],
            'unsafe key'          => [
				[
					[
						[
							'type'     => 'customer_role',
							'operator' => 'in',
							'value'    => [ '<script>' ],
						],
                    ],
                ],
            ],
            'non-numeric id'      => [
				[
					[
						[
							'type'     => 'product_in_cart',
							'operator' => 'in',
							'value'    => [ 'abc' ],
						],
                    ],
                ],
            ],
            'unknown device'      => [
				[
					[
						[
							'type'     => 'device',
							'operator' => 'is',
							'value'    => 'tablet',
						],
                    ],
                ],
            ],
            'negative amount'     => [
				[
					[
						[
							'type'     => 'cart_total',
							'operator' => 'gt',
							'value'    => -1,
						],
                    ],
                ],
            ],
            'amount as string'    => [
				[
					[
						[
							'type'     => 'cart_total',
							'operator' => 'gt',
							'value'    => '150',
						],
                    ],
                ],
            ],
        ];
    }

    public function test_replies_carry_the_names_of_the_products_a_rule_names(): void {
        $id      = LayoutFactory::create_draft();
        $product = new \WC_Product_Simple();
        $product->set_name( 'Hoodie' );
        $product->save();
        $deleted_id = 999999;

        $saved  = $this->put(
            $id,
            [
				[
					[
						'type'     => 'product_in_cart',
						'operator' => 'in',
						'value'    => [ $product->get_id(), $deleted_id ],
					],
				],
            ]
        );
        $loaded = $this->get( $id );

        // Round-tripped through JSON, as the SPA sees it: a deleted product has no name, and no names is still an object.
        foreach ( [ $saved, $loaded ] as $response ) {
            $labels = json_decode( wp_json_encode( $response->get_data()['labels'] ), true );
            $this->assertSame( [ 'products' => [ (string) $product->get_id() => 'Hoodie' ] ], $labels );
        }

        $this->put( $id, [] );
        $this->assertSame( '{"products":{}}', wp_json_encode( $this->get( $id )->get_data()['labels'] ) );
    }

    public function test_missing_layout_is_not_found(): void {
        $this->assertSame( 404, $this->get( 999999 )->get_status() );
        $this->assertSame( 404, $this->put( 999999, [] )->get_status() );
    }

    public function test_requires_manage_woocommerce(): void {
        $id = LayoutFactory::create_draft();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

        $this->assertSame( 403, $this->get( $id )->get_status() );
        $this->assertSame( 403, $this->put( $id, [] )->get_status() );
    }
}
