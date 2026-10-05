<?php
namespace YayCheckout\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;
use YayCheckout\Controllers\LayoutTemplateCatalogue;
use YayCheckout\Models\Design;
use YayCheckout\Models\Settings;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Support\Plan;
use YayCheckout\Tests\Support\LayoutFactory;

/** POST /setup saves the draft, design and setup flag together, or nothing at all. */
class SetupRestTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        delete_option( Design::OPTION_DESIGN );
        delete_option( Settings::OPTION_SETUP_DONE );
    }

    private function post( array $body ) {
        $request = new WP_REST_Request( 'POST', '/yaycheckout/v1/setup' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( $body ) );

        return rest_do_request( $request );
    }

    private function body( array $tokens ): array {
        return [
            'template_slug' => LayoutTemplateCatalogue::templates( false )[0]['slug'],
            'design'        => [
                'tokens'     => $tokens,
                'preset_id'  => null,
                'custom_css' => '',
            ],
        ];
    }

    private function assert_nothing_written( int $layouts_before ): void {
        $this->assertSame( $layouts_before, ( new LayoutRepository() )->count() );
        $this->assertFalse( get_option( Design::OPTION_DESIGN ) );
        $this->assertFalse( get_option( Settings::OPTION_SETUP_DONE ) );
    }

    public function test_creates_the_draft_saves_the_design_and_marks_setup_done(): void {
        $tokens                       = Design::defaults();
        $tokens['buttons']['enabled'] = true;
        $tokens['buttons']['radius']  = 12;

        $response = $this->post( $this->body( $tokens ) );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( 'draft', $data['layout']['status'] );
        $this->assertSame( 12, $data['design']['tokens']['buttons']['radius'] );
        $this->assertSame( 1, ( new LayoutRepository() )->count() );
        $this->assertTrue( ( new SettingsRepository() )->is_setup_done() );
    }

    public function test_invalid_tokens_write_nothing(): void {
        $tokens                      = Design::defaults();
        $tokens['buttons']['radius'] = 'round';

        $response = $this->post( $this->body( $tokens ) );

        $this->assertSame( 400, $response->get_status() );
        $this->assert_nothing_written( 0 );
    }

    public function test_unknown_template_writes_nothing(): void {
        $body                  = $this->body( Design::defaults() );
        $body['template_slug'] = 'no-such-template';

        $response = $this->post( $body );

        $this->assertSame( 400, $response->get_status() );
        $this->assert_nothing_written( 0 );
    }

    /** Runs $test on the Free plan; the test bootstrap boots Pro whenever includes/Pro exists. */
    private function as_free( callable $test ): void {
        $pro = new \ReflectionProperty( Plan::class, 'pro' );
        $pro->setAccessible( true );
        $was_pro = $pro->getValue();
        $pro->setValue( null, false );

        try {
            $test();
        } finally {
            $pro->setValue( null, $was_pro );
        }
    }

    public function test_free_layout_limit_writes_nothing(): void {
        for ( $i = 0; $i < LayoutRepository::FREE_LIMIT; $i++ ) {
            LayoutFactory::create_draft();
        }

        $this->as_free(
            function () {
                $response = $this->post( $this->body( Design::defaults() ) );

                $this->assertSame( 400, $response->get_status() );
                $this->assert_nothing_written( LayoutRepository::FREE_LIMIT );
            }
        );
    }

    public function test_locked_template_on_free_writes_nothing(): void {
        $locked                = array_values( array_filter( LayoutTemplateCatalogue::templates( false ), fn( $t ) => $t['locked'] ) );
        $body                  = $this->body( Design::defaults() );
        $body['template_slug'] = $locked[0]['slug'];

        $this->as_free(
            function () use ( $body ) {
                $response = $this->post( $body );

                $this->assertSame( 400, $response->get_status() );
                $this->assert_nothing_written( 0 );
            }
        );
    }

    public function test_missing_template_slug_is_refused_at_the_route(): void {
        $body                  = $this->body( Design::defaults() );
        $body['template_slug'] = [];

        $response = $this->post( $body );

        $this->assertSame( 400, $response->get_status() );
        $this->assert_nothing_written( 0 );
    }

    public function test_second_setup_is_refused_and_writes_nothing(): void {
        $this->assertSame( 200, $this->post( $this->body( Design::defaults() ) )->get_status() );
        $design_after_first = get_option( Design::OPTION_DESIGN );

        $tokens                      = Design::defaults();
        $tokens['buttons']['radius'] = 20;
        $response                    = $this->post( $this->body( $tokens ) );

        $this->assertSame( 409, $response->get_status() );
        $this->assertSame( 1, ( new LayoutRepository() )->count() );
        $this->assertSame( $design_after_first, get_option( Design::OPTION_DESIGN ) );
    }
}
