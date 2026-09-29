<?php
namespace YayCheckout\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
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
						'type'     => 'customer_role',
						'operator' => 'in',
						'value'    => [ 'customer', 'wholesale', 'customer' ],
					],
                    [
						'type'     => 'device',
						'operator' => 'is',
						'value'    => 'desktop',
                    ],
                ],
                [
                    [
						'type'     => 'login_status',
						'operator' => 'is_not',
						'value'    => 'guest',
					],
                ],
            ]
        );

        $expected = [
            [
                [
					'type'     => 'customer_role',
					'operator' => 'in',
					'value'    => [ 'customer', 'wholesale' ],
				],
                [
					'type'     => 'device',
					'operator' => 'is',
					'value'    => 'desktop',
                ],
            ],
            [
                [
					'type'     => 'login_status',
					'operator' => 'is_not',
					'value'    => 'guest',
				],
            ],
        ];

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( [ 'rules' => $expected ], $response->get_data() );
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
						'type'     => 'customer_role',
						'operator' => 'in',
						'value'    => [ 'customer' ],
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
							'type'     => 'customer_role',
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
							'type'     => 'customer_role',
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
            'bad login status'    => [
				[
					[
						[
							'type'     => 'login_status',
							'operator' => 'is',
							'value'    => 'member',
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
            'device all'          => [
				[
					[
						[
							'type'     => 'device',
							'operator' => 'is',
							'value'    => 'all',
						],
                    ],
                ],
            ],
            'removed country'     => [
				[
					[
						[
							'type'     => 'country',
							'operator' => 'in',
							'value'    => [ 'DE' ],
						],
                    ],
                ],
            ],
            'removed cart total'  => [
				[
					[
						[
							'type'     => 'cart_total',
							'operator' => 'gt',
							'value'    => 150,
						],
                    ],
                ],
            ],
        ];
    }

    public function test_keys_are_deduplicated_as_strings(): void {
        $rules = Layout::sanitize_rules(
            [
                [
                    [
                        'type'     => 'customer_role',
                        'operator' => 'in',
                        'value'    => [ '10', '1e1', '10' ],
                    ],
                ],
            ]
        );

        $this->assertSame( [ '10', '1e1' ], $rules[0][0]['value'] );
    }

    public function test_duplicate_copies_the_rules(): void {
        $id    = LayoutFactory::create_draft();
        $rules = [
            [
                [
                    'type'     => 'device',
                    'operator' => 'is',
                    'value'    => 'mobile',
                ],
            ],
        ];
        $this->put( $id, $rules );

        $copy = ( new LayoutRepository() )->duplicate( $id );

        $this->assertSame( $rules, get_post_meta( $copy->id, Layout::META_RULES, true ) );
        $this->assertSame( $rules, $copy->rules );
    }

    public function test_the_layouts_list_carries_each_layouts_rules(): void {
        $with    = LayoutFactory::create_draft();
        $without = LayoutFactory::create_draft();
        $rules   = [
            [
                [
                    'type'     => 'device',
                    'operator' => 'is',
                    'value'    => 'mobile',
                ],
            ],
        ];
        $this->put( $with, $rules );

        $items = rest_do_request( new WP_REST_Request( 'GET', '/yaycheckout/v1/layouts' ) )->get_data()['items'];
        $by_id = array_column( $items, 'rules', 'id' );

        $this->assertSame( $rules, $by_id[ $with ] );
        $this->assertSame( [], $by_id[ $without ] );
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
