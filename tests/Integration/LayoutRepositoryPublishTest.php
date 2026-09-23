<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

/** A layout publishes only with a Checkout block (any depth) and extra fields keyed uniquely. */
class LayoutRepositoryPublishTest extends WP_UnitTestCase {

    public function test_publishes_with_checkout_nested_in_a_group(): void {
        $id = LayoutFactory::create_draft(
            [ 'post_content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:yaycheckout/checkout /--></div><!-- /wp:group -->' ]
        );

        $this->assertSame( 'publish', ( new LayoutRepository() )->publish( $id )->post_status );
    }

    public function test_publish_and_activate_refuse_a_layout_without_checkout(): void {
        $id         = LayoutFactory::create_draft( [ 'post_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ] );
        $repository = new LayoutRepository();

        foreach ( [ 'publish', 'activate' ] as $method ) {
            try {
                $repository->$method( $id );
                $this->fail( "$method should throw." );
            } catch ( \InvalidArgumentException $ex ) {
                $this->assertSame( 'draft', get_post( $id )->post_status, $method );
            }
        }
    }

    /** @dataProvider bad_extra_field_keys */
    public function test_publish_refuses_empty_or_duplicate_extra_field_keys( string $fields, string $message ): void {
        $id = LayoutFactory::create_draft(
            [ 'post_content' => '<!-- wp:yaycheckout/checkout -->' . $fields . '<!-- /wp:yaycheckout/checkout -->' ]
        );

        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( $message );

        ( new LayoutRepository() )->publish( $id );
    }

    /** A layout published some other way still cannot go live without passing the same rules. */
    public function test_activate_refuses_an_already_published_layout_without_checkout(): void {
        $id = LayoutFactory::create_draft(
            [
                'post_status'  => 'publish',
                'post_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
            ]
        );

        try {
            ( new LayoutRepository() )->activate( $id );
            $this->fail( 'activate should throw.' );
        } catch ( \InvalidArgumentException $ex ) {
            $this->assertNotContains( $id, (array) get_option( Layout::OPTION_ACTIVE, [] ) );
        }
    }

    /** @dataProvider core_rest_saves */
    public function test_core_rest_refuses_publishing_an_unpublishable_layout( string $status, string $stored, array $body, int $expected ): void {
        LayoutFactory::as_shop_manager();
        $id      = LayoutFactory::create_draft(
            [
                'post_status'  => $status,
                'post_content' => $stored,
            ]
        );
        $request = new \WP_REST_Request( 'POST', '/wp/v2/' . Layout::POST_TYPE . '/' . $id );
        $request->set_body_params( $body );

        $response = rest_do_request( $request );

        $this->assertSame( $expected, $response->get_status() );

        if ( 400 === $expected ) {
            $this->assertSame( 'yaycheckout_layout_unpublishable', $response->get_data()['code'] );
            $this->assertSame( $stored, get_post( $id )->post_content );
        }
    }

    public function core_rest_saves(): array {
        $no_checkout = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->';
        $bad_key     = LayoutFactory::checkout_markup() . '<!-- wp:yaycheckout/extra-field {"fieldKey":"_billing_email"} /-->';
        $valid       = LayoutFactory::checkout_markup();

        return [
            'publish without checkout'        => [
                'draft',
                $valid,
                [
                    'status'  => 'publish',
                    'content' => $no_checkout,
                ],
                400,
            ],
            'schedule without checkout'       => [
                'draft',
                $valid,
                [
                    'status'  => 'future',
                    'date'    => '2099-01-01T00:00:00',
                    'content' => $no_checkout,
                ],
                400,
            ],
            'publish with a bad key'          => [
                'draft',
                $valid,
                [
                    'status'  => 'publish',
                    'content' => $bad_key,
                ],
                400,
            ],
            'publish a stored bad draft'      => [ 'draft', $no_checkout, [ 'status' => 'publish' ], 400 ],
            'edit live layout to no checkout' => [ 'publish', $valid, [ 'content' => $no_checkout ], 400 ],
            'draft without checkout saves'    => [ 'draft', $valid, [ 'content' => $no_checkout ], 200 ],
            'publish a valid layout'          => [ 'draft', $valid, [ 'status' => 'publish' ], 200 ],
            'fix a stored bad draft'          => [
                'draft',
                $no_checkout,
                [
                    'status'  => 'publish',
                    'content' => $valid,
                ],
                200,
            ],
        ];
    }

    public function bad_extra_field_keys(): array {
        return [
            'empty key'        => [ '<!-- wp:yaycheckout/extra-field {"fieldKey":"vat"} /--><!-- wp:yaycheckout/extra-field {"fieldKey":"  "} /-->', 'needs a Key' ],
            'space in key'     => [ '<!-- wp:yaycheckout/extra-field {"fieldKey":"vat number"} /-->', 'may use only letters, numbers and underscores' ],
            'dot in key'       => [ '<!-- wp:yaycheckout/extra-field {"fieldKey":"vat.no"} /-->', 'may use only letters, numbers and underscores' ],
            'woo internal key' => [ '<!-- wp:yaycheckout/extra-field {"fieldKey":"_billing_email"} /-->', 'must not start with an underscore: _billing_email.' ],
            'duplicate key'    => [ '<!-- wp:yaycheckout/extra-field {"fieldKey":"vat"} /--><!-- wp:yaycheckout/extra-field {"fieldKey":" vat"} /-->', 'Used more than once: vat.' ],
        ];
    }
}
