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

    public function test_free_layout_limit_writes_nothing(): void {
        if ( Plan::is_pro() ) {
            $this->markTestSkipped( 'Pro has no layout limit.' );
        }

        for ( $i = 0; $i < LayoutRepository::FREE_LIMIT; $i++ ) {
            LayoutFactory::create_draft();
        }

        $response = $this->post( $this->body( Design::defaults() ) );

        $this->assertSame( 400, $response->get_status() );
        $this->assert_nothing_written( LayoutRepository::FREE_LIMIT );
    }
}
