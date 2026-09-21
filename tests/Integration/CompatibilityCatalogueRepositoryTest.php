<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Repositories\CompatibilityCatalogueRepository;
use YayCheckout\Support\CompatibilityCatalogue;

/** A dead catalogue endpoint costs one attempt per TTL_FALLBACK, never one per page view. */
class CompatibilityCatalogueRepositoryTest extends WP_UnitTestCase {

    private int $http_calls = 0;

    /** @var mixed */
    private $http_response;

    public function set_up(): void {
        parent::set_up();
        delete_transient( CompatibilityCatalogue::TRANSIENT );
        $this->http_calls    = 0;
        $this->http_response = new \WP_Error( 'http_request_failed', 'no network in tests' );
        add_filter( 'pre_http_request', [ $this, 'short_circuit_http' ] );
    }

    public function short_circuit_http() {
        ++$this->http_calls;

        return $this->http_response;
    }

    private function remote_ok( array $document ): void {
        $this->http_response = [
            'response' => [ 'code' => 200 ],
            'body'     => wp_json_encode( $document ),
        ];
    }

    private function valid_document(): array {
        return [
            'version'  => 1,
            'gateways' => [
                'stripe' => [
                    'status'      => 'verified',
                    'express_pay' => 'Apple Pay',
                ],
            ],
            'plugins'  => [],
        ];
    }

    public function test_remote_success_is_cached(): void {
        $this->remote_ok( $this->valid_document() );
        $repository = new CompatibilityCatalogueRepository();

        $catalogue = $repository->get();

        $this->assertSame( CompatibilityCatalogue::SOURCE_REMOTE, $catalogue['source'] );
        $this->assertNotNull( $catalogue['fetched_at'] );
        $this->assertSame( 'Apple Pay', $catalogue['gateways']['stripe']['express_pay'] );
        $this->assertSame( 1, $this->http_calls );

        $this->assertSame( $catalogue, $repository->get() );
        $this->assertSame( 1, $this->http_calls );
    }

    public function test_remote_failure_falls_back_to_bundled_and_is_negatively_cached(): void {
        $this->http_response = new \WP_Error( 'http_request_failed', 'down' );
        $repository          = new CompatibilityCatalogueRepository();

        $catalogue = $repository->get();

        $this->assertSame( CompatibilityCatalogue::SOURCE_BUNDLED, $catalogue['source'] );
        $this->assertNull( $catalogue['fetched_at'] );
        $this->assertArrayHasKey( 'cod', $catalogue['gateways'] );

        $repository->get();
        $this->assertSame( 1, $this->http_calls );
    }

    public function test_remote_document_off_contract_falls_back_to_bundled(): void {
        $this->remote_ok( [ 'version' => 2 ] + $this->valid_document() );

        $this->assertSame( CompatibilityCatalogue::SOURCE_BUNDLED, ( new CompatibilityCatalogueRepository() )->get()['source'] );
    }

    public function test_non_200_or_non_json_responses_fall_back_to_bundled(): void {
        $this->http_response = [
            'response' => [ 'code' => 404 ],
            'body'     => wp_json_encode( $this->valid_document() ),
        ];
        $this->assertSame( CompatibilityCatalogue::SOURCE_BUNDLED, ( new CompatibilityCatalogueRepository() )->get( true )['source'] );

        $this->http_response = [
            'response' => [ 'code' => 200 ],
            'body'     => '<html>not json</html>',
        ];
        $this->assertSame( CompatibilityCatalogue::SOURCE_BUNDLED, ( new CompatibilityCatalogueRepository() )->get( true )['source'] );
    }

    public function test_refresh_refetches_over_a_warm_cache(): void {
        $this->remote_ok( $this->valid_document() );
        $repository = new CompatibilityCatalogueRepository();

        $repository->get();
        $repository->get( true );

        $this->assertSame( 2, $this->http_calls );
    }
}
