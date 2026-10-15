<?php
namespace YayCheckout\Tests\Integration\Pro;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Pro\Services\LayoutImportReview;
use YayCheckout\Tests\Support\LayoutFactory;

/** An imported layout keeps the file's logged-out view, and a file without a readable one imports with none. */
class LayoutImportReviewTest extends WP_UnitTestCase {

    private static function envelope( array $layout ): array {
        return [
            'format'         => Layout::EXPORT_FORMAT,
            'plugin_version' => '1',
            'exported_at'    => '2026-10-15T00:00:00Z',
            'layouts'        => [
                array_merge(
                    [
                        'title'   => 'T',
                        'content' => LayoutFactory::checkout_markup(),
                    ],
                    $layout
                ),
            ],
        ];
    }

    public function test_logged_out_content_is_kept_verbatim(): void {
        $markup = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/login /--><!-- /wp:yaycheckout/checkout -->';

        $this->assertSame( $markup, LayoutImportReview::from_envelope( self::envelope( [ 'logged_out_content' => $markup ] ) )['logged_out_content'] );
    }

    public function test_missing_or_non_string_logged_out_content_reads_as_empty(): void {
        foreach ( [ [], [ 'logged_out_content' => null ], [ 'logged_out_content' => 5 ], [ 'logged_out_content' => [ 'x' ] ] ] as $extra ) {
            $this->assertSame( '', LayoutImportReview::from_envelope( self::envelope( $extra ) )['logged_out_content'], wp_json_encode( $extra ) );
        }
    }
}
