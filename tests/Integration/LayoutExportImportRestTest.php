<?php
namespace YayCheckout\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Tests\Support\LayoutFactory;

/** Pins the export file's shape (rules included) and name, that it re-imports as a draft, and which files import refuses. */
class LayoutExportImportRestTest extends WP_UnitTestCase {

    private const MARKUP = '<!-- wp:group --><div class="wp-block-group"><!-- wp:yaycheckout/checkout /--></div><!-- /wp:group -->';

    public function set_up(): void {
        parent::set_up();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
    }

    private function export( int $id ) {
        return rest_do_request( new WP_REST_Request( 'GET', "/yaycheckout/v1/layouts/$id/export" ) );
    }

    private function import( array $file ) {
        $request = new WP_REST_Request( 'POST', '/yaycheckout/v1/layouts/import' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( [ 'file' => $file ] ) );

        return rest_do_request( $request );
    }

    private function review( array $file ) {
        $request = new WP_REST_Request( 'POST', '/yaycheckout/v1/layouts/import/review' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( [ 'file' => $file ] ) );

        return rest_do_request( $request );
    }

    private function envelope( array $layouts ): array {
        return [
            'format'         => Layout::EXPORT_FORMAT,
            'plugin_version' => '1.0.0',
            'exported_at'    => '2026-09-24T00:00:00Z',
            'layouts'        => $layouts,
        ];
    }

    public function test_export_carries_title_markup_and_rules_and_names_the_file(): void {
        $id    = LayoutFactory::create_draft(
            [
                'post_title'   => 'B2B Checkout ✨',
                'post_status'  => 'publish',
                'post_content' => self::MARKUP,
            ]
        );
        $rules = [
            [
                [
                    'type'     => 'customer_role',
                    'operator' => 'in',
                    'value'    => [ 'wholesale' ],
                ],
            ],
        ];
        update_post_meta( $id, Layout::META_RULES, $rules );

        $response = $this->export( $id );
        $data     = $response->get_data();

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( [ 'format', 'plugin_version', 'exported_at', 'layouts' ], array_keys( $data ) );
        $this->assertSame(
            [
                [
                    'title'   => 'B2B Checkout ✨',
                    'content' => self::MARKUP,
                    'rules'   => $rules,
                ],
            ],
            $data['layouts']
        );
        $this->assertMatchesRegularExpression(
            '/^attachment; filename="yaycheckout-b2b-checkout-\d{8}\.json"$/',
            $response->get_headers()['Content-Disposition']
        );
    }

    public function test_an_exported_file_imports_as_a_new_draft(): void {
        $id   = LayoutFactory::create_draft(
            [
                'post_status'  => 'publish',
                'post_content' => self::MARKUP,
            ]
        );
        $file = $this->export( $id )->get_data();

        $response = $this->import( $file );
        $created  = $response->get_data();

        $this->assertSame( 201, $response->get_status() );
        $this->assertNotSame( $id, $created['id'] );
        $this->assertSame( 'draft', $created['status'] );
        $this->assertSame( self::MARKUP, get_post( $created['id'] )->post_content );
    }

    /** Stored as the file holds them, even values this store lacks: the merchant edits those in Assignment. */
    public function test_import_keeps_the_rules_an_export_carries(): void {
        $id    = LayoutFactory::create_draft( [ 'post_content' => self::MARKUP ] );
        $rules = [
            [
                [
                    'type'     => 'customer_role',
                    'operator' => 'in',
                    'value'    => [ 'wholesale' ],
                ],
                [
                    'type'     => 'login_status',
                    'operator' => 'is',
                    'value'    => 'logged_in',
                ],
            ],
        ];
        update_post_meta( $id, Layout::META_RULES, $rules );

        $created = $this->import( $this->export( $id )->get_data() )->get_data();

        $this->assertSame( $rules, get_post_meta( $created['id'], Layout::META_RULES, true ) );
        $this->assertSame( 'imported', $created['rules_import']['status'] );
    }

    public function test_import_drops_rules_it_cannot_read_but_still_creates_the_draft(): void {
        $file = $this->envelope(
            [
                [
                    'title'   => 'Newer plugin',
                    'content' => self::MARKUP,
                    'rules'   => [
                        [
                            [
                                'type'     => 'weather',
                                'operator' => 'is',
                                'value'    => 'sunny',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $response = $this->import( $file );
        $created  = $response->get_data();

        $this->assertSame( 201, $response->get_status() );
        $this->assertSame( 'dropped', $created['rules_import']['status'] );
        $this->assertFalse( metadata_exists( 'post', $created['id'], Layout::META_RULES ) );
    }

    public function test_import_of_a_file_without_rules_reports_none(): void {
        $file = $this->envelope(
            [
                [
                    'title'   => 'Old export',
                    'content' => self::MARKUP,
                ],
            ]
        );

        $this->assertSame( 'none', $this->import( $file )->get_data()['rules_import']['status'] );
    }

    /** @dataProvider refused_files */
    public function test_import_refuses( array $file ): void {
        $before = wp_count_posts( Layout::POST_TYPE )->draft;

        $response = $this->import( $file );

        $this->assertSame( 400, $response->get_status() );
        $this->assertSame( $before, wp_count_posts( Layout::POST_TYPE )->draft );
    }

    /** The editor's Import reads the layout from here, so nothing is stored and the refusals match import. */
    public function test_review_returns_the_layout_without_storing_it(): void {
        $before = wp_count_posts( Layout::POST_TYPE )->draft;

        $response = $this->review(
            $this->envelope(
                [
                    [
                        'title'   => 'Imported',
                        'content' => self::MARKUP,
                    ],
                ]
            )
        );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame(
            [
                'title'     => 'Imported',
                'content'   => self::MARKUP,
                'blocks'    => 1,
                'has_rules' => false,
            ],
            $response->get_data()
        );
        $this->assertSame( $before, wp_count_posts( Layout::POST_TYPE )->draft );
    }

    /** The editor imports content only; has_rules lets it tell the merchant the file's rules stay behind. */
    public function test_review_says_when_the_file_carries_rules(): void {
        $file = $this->envelope(
            [
                [
                    'title'   => 'With rules',
                    'content' => self::MARKUP,
                    'rules'   => [
                        [
                            [
                                'type'     => 'device',
                                'operator' => 'is',
                                'value'    => 'mobile',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->assertTrue( $this->review( $file )->get_data()['has_rules'] );
    }

    /** Rules that import would drop do not count: has_rules sends the merchant to Tools → Import for them. */
    public function test_review_does_not_count_rules_import_would_drop(): void {
        $file = $this->envelope(
            [
                [
                    'title'   => 'Newer plugin',
                    'content' => self::MARKUP,
                    'rules'   => [
                        [
                            [
                                'type'     => 'weather',
                                'operator' => 'is',
                                'value'    => 'sunny',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->assertFalse( $this->review( $file )->get_data()['has_rules'] );
    }

    /** @dataProvider refused_files */
    public function test_review_refuses( array $file ): void {
        $this->assertSame( 400, $this->review( $file )->get_status() );
    }

    public function refused_files(): array {
        $layout = [
            'title'   => 'Imported',
            'content' => self::MARKUP,
        ];

        return [
            'foreign format'    => [ array_merge( $this->envelope( [ $layout ] ), [ 'format' => 'something-else' ] ) ],
            'no layout'         => [ $this->envelope( [] ) ],
            'two layouts'       => [ $this->envelope( [ $layout, $layout ] ) ],
            'no checkout block' => [
                $this->envelope(
                    [
                        [
                            'title'   => 'Imported',
                            'content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
                        ],
                    ]
                ),
            ],
        ];
    }

    /** Import only needs the root block; blocks this site lacks pass and the editor flags them. */
    public function test_import_accepts_blocks_the_site_does_not_register(): void {
        $content = '<!-- wp:yaycheckout/checkout --><!-- wp:acme/unknown /--><!-- /wp:yaycheckout/checkout -->';

        $response = $this->import(
            $this->envelope(
                [
                    [
                        'title'   => 'Imported',
                        'content' => $content,
                    ],
                ]
            )
        );

        $this->assertSame( 201, $response->get_status() );
    }
}
