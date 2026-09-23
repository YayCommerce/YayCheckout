<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Controllers\LayoutTemplateCatalogue;

/** Pins the v1.1 template list and which of it the Free plan unlocks; the Free set is "first N by order", so order is part of the contract. */
class LayoutTemplateCatalogueTest extends WP_UnitTestCase {

    private const SLUGS = [
        'classic-two-column',
        'split-express',
        'editorial-fashion',
        'multi-step-wizard',
        'accordion-one-page',
        'upsell-cross-sell',
        'minimal-steps',
        'live-receipt',
        'soft-steps',
        'material-stepper',
        'digital-product',
    ];

    private const FREE_SLUGS = [ 'classic-two-column', 'split-express', 'editorial-fashion' ];

    public function test_catalogue_matches_the_v1_1_list_in_order(): void {
        $templates = LayoutTemplateCatalogue::templates( true );

        $this->assertSame( self::SLUGS, array_column( $templates, 'slug' ) );

        foreach ( $templates as $template ) {
            $this->assertNotSame( '', $template['name'], $template['slug'] );
            $this->assertContains(
                $template['category'],
                [ 'Two column', 'Multi-step', 'One column', 'Digital goods' ],
                $template['slug']
            );
        }
    }

    public function test_free_unlocks_exactly_the_three_lite_templates(): void {
        $locked = array_column( LayoutTemplateCatalogue::templates( false ), 'locked', 'slug' );

        $this->assertCount( 11, $locked );

        foreach ( self::SLUGS as $slug ) {
            $this->assertSame( ! in_array( $slug, self::FREE_SLUGS, true ), $locked[ $slug ], $slug );
        }
    }

    public function test_pro_unlocks_everything(): void {
        $locked = array_column( LayoutTemplateCatalogue::templates( true ), 'locked' );

        $this->assertCount( 11, $locked );
        $this->assertNotContains( true, $locked );
    }

    public function test_has_template_only_knows_the_current_list(): void {
        $this->assertTrue( LayoutTemplateCatalogue::has_template( 'digital-product' ) );
        $this->assertFalse( LayoutTemplateCatalogue::has_template( 'minimal' ) );
        $this->assertFalse( LayoutTemplateCatalogue::has_template( '' ) );
    }
}
