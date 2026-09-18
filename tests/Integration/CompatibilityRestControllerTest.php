<?php
namespace YayCheckout\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;
use YayCheckout\Support\CompatibilityCatalogue;

/** Pins the wire shape of GET /compatibility, its permission gate and that refresh is the only thing that refetches. */
class CompatibilityRestControllerTest extends WP_UnitTestCase {

    private const ROUTE = '/yaycheckout/v1/compatibility';

    private int $http_calls = 0;

    public function set_up(): void {
        parent::set_up();
        delete_transient( CompatibilityCatalogue::TRANSIENT );
        $this->http_calls = 0;
        add_filter( 'pre_http_request', [ $this, 'serve_catalogue' ] );
    }

    public function serve_catalogue() {
        ++$this->http_calls;

        return [
            'response' => [ 'code' => 200 ],
            'body'     => wp_json_encode(
                [
                    'version'  => 1,
                    'gateways' => [],
                    'plugins'  => [],
                ]
            ),
        ];
    }

    private function as_admin(): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
    }

    private function get( array $params = [] ) {
        $request = new WP_REST_Request( 'GET', self::ROUTE );
        $request->set_query_params( $params );

        return rest_do_request( $request );
    }

    public function test_admin_gets_the_wire_shape(): void {
        $this->as_admin();

        $response = $this->get();
        $data     = $response->get_data();

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( [ 'store', 'gateways', 'plugins', 'catalogue' ], array_keys( $data ) );
        $this->assertSame( [ 'source', 'fetched_at' ], array_keys( $data['catalogue'] ) );
        $this->assertSame( CompatibilityCatalogue::SOURCE_REMOTE, $data['catalogue']['source'] );
        $this->assertIsString( $data['store']['store_type'] );
        $this->assertSame( array_values( $data['gateways'] ), $data['gateways'] );
        $this->assertSame( array_values( $data['plugins'] ), $data['plugins'] );
    }

    public function test_permission_gate(): void {
        $this->assertSame( 401, $this->get()->get_status() );

        wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
        $this->assertSame( 403, $this->get()->get_status() );
    }

    public function test_refresh_is_the_only_refetch(): void {
        $this->as_admin();

        $this->get();
        $this->get( [ 'refresh' => 'false' ] );
        $this->assertSame( 1, $this->http_calls );

        $this->get( [ 'refresh' => 'true' ] );
        $this->assertSame( 2, $this->http_calls );
    }

    public function test_refresh_must_be_boolean(): void {
        $this->as_admin();

        $response = $this->get( [ 'refresh' => 'maybe' ] );

        $this->assertSame( 400, $response->get_status() );
        $this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
    }
}
