<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Controllers\LayoutTemplateCatalogue;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Services\LayoutMarkupReader;

/** Every template a merchant can create from yields a publishable layout with its whole design. */
class LayoutTemplateCatalogueTest extends WP_UnitTestCase {

    public function test_every_available_template_is_publishable_and_sets_every_section(): void {
        $available = array_filter( LayoutTemplateCatalogue::templates( true ), static fn( $t ) => $t['available'] );
        $this->assertContains( LayoutTemplateCatalogue::DEFAULT_SLUG, array_column( $available, 'slug' ) );

        foreach ( array_column( $available, 'slug' ) as $slug ) {
            $this->assertSame( '', LayoutMarkupReader::publish_error( LayoutTemplateCatalogue::markup( $slug ), BlockRegistry::locked_blocks() ), $slug );

            $folder  = is_dir( YAYCHECKOUT_PLUGIN_DIR . "templates/layouts/pro/{$slug}" ) ? "pro/{$slug}" : $slug;
            $decoded = wp_json_file_decode( YAYCHECKOUT_PLUGIN_DIR . "templates/layouts/{$folder}/design.json", [ 'associative' => true ] );
            $this->assertIsArray( $decoded, $slug );
            foreach ( array_keys( LayoutDesign::schema() ) as $section ) {
                $this->assertIsArray( $decoded[ $section ] ?? null, "{$slug}: {$section}" );
            }
            // Same keys, order and types: nothing in the file was dropped or defaulted by the projection.
            $this->assertSame( $decoded, LayoutTemplateCatalogue::design( $slug )->to_meta(), $slug );
        }
    }

    public function test_free_lists_no_pro_template(): void {
        $free = array_column( LayoutTemplateCatalogue::templates( false ), 'slug' );
        $this->assertContains( LayoutTemplateCatalogue::DEFAULT_SLUG, $free );
        $this->assertNotContains( 'multi-step-wizard', $free );
        $this->assertContains( 'multi-step-wizard', array_column( LayoutTemplateCatalogue::templates( true ), 'slug' ) );
    }

    public function test_a_template_without_files_is_coming_soon(): void {
        $available = array_column( LayoutTemplateCatalogue::templates( true ), 'available', 'slug' );
        $this->assertFalse( $available['material-stepper'] );

        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'This template is coming soon.' );
        LayoutTemplateCatalogue::assert_available( 'material-stepper' );
    }
}
