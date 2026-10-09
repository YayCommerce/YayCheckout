<?php
namespace YayCheckout\Tests\Integration\Pro;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Tests\Support\LayoutFactory;

/** Import keeps a file's valid design sections, drops invalid ones without refusing the file, and adds nothing for a file without design. */
class LayoutImportDesignTest extends WP_UnitTestCase {

    private const COLOURS = [
        'enabled'    => true,
        'primary'    => '#171719',
        'text'       => '#171719',
        'background' => '#ffffff',
        'border'     => '#e4e4e7',
    ];

    public function set_up(): void {
        parent::set_up();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
    }

    private function import( array $entry ): \WP_REST_Response {
        $request = new \WP_REST_Request( 'POST', '/yaycheckout/v1/layouts/import' );
        $request->set_body_params(
            [
                'file' => [
                    'format'  => Layout::EXPORT_FORMAT,
                    'layouts' => [
                        array_merge(
                            [
                                'title'   => 'Imported',
                                'content' => LayoutFactory::checkout_markup(),
                            ],
                            $entry
                        ),
                    ],
                ],
            ]
        );

        return rest_do_request( $request );
    }

    public function test_valid_sections_are_kept_and_invalid_ones_dropped(): void {
        $response = $this->import(
            [
                'design' => [
                    'tokens'     => [
                        'colours' => self::COLOURS,
                        'inputs'  => [
                            'enabled' => true,
                            'style'   => 'wavy',
                        ],
                    ],
                    'custom_css' => 'a{color:red}',
                ],
            ]
        );

        $this->assertSame( 201, $response->get_status() );
        $this->assertSame(
            [
                'tokens'     => [ 'colours' => self::COLOURS ],
                'custom_css' => 'a{color:red}',
            ],
            get_post_meta( $response->get_data()['id'], Layout::META_DESIGN, true )
        );
    }

    public function test_disallowed_css_is_dropped_not_refused(): void {
        $response = $this->import(
            [
                'design' => [
                    'tokens'     => [ 'colours' => self::COLOURS ],
                    'custom_css' => '</style><script>',
                ],
            ]
        );

        $this->assertSame( 201, $response->get_status() );
        $this->assertSame( [ 'tokens' => [ 'colours' => self::COLOURS ] ], get_post_meta( $response->get_data()['id'], Layout::META_DESIGN, true ) );
    }

    public function test_file_without_design_adds_no_meta(): void {
        $response = $this->import( [] );

        $this->assertSame( 201, $response->get_status() );
        $this->assertFalse( metadata_exists( 'post', $response->get_data()['id'], Layout::META_DESIGN ) );
    }
}
