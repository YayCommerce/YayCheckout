<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Controllers\LayoutTemplateCatalogue;
use YayCheckout\Helpers\LayoutCheckFacts;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Renderer\WcHooks;
use YayCheckout\Services\LayoutCheck;
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

    /** WooCommerce's before-form hook fires only where a Hook block places it, and plugins print notices there (YayCurrency). */
    public function test_every_template_places_the_before_checkout_form_hook(): void {
        $available = array_filter( LayoutTemplateCatalogue::templates( true ), static fn( $t ) => $t['available'] );

        foreach ( array_column( $available, 'slug' ) as $slug ) {
            $this->assertContains( 'woocommerce_before_checkout_form', LayoutMarkupReader::wc_hooks( LayoutTemplateCatalogue::markup( $slug ) ), $slug );
        }
    }

    /** Run test finds nothing that stops an order in a layout fresh from any template, on a fully set-up store. */
    public function test_every_available_template_passes_the_layout_check(): void {
        $available = array_filter( LayoutTemplateCatalogue::templates( true ), static fn( $t ) => $t['available'] );
        $facts     = [
            'locked_blocks'           => BlockRegistry::locked_blocks(),
            'terms_page'              => true,
            'ships'                   => true,
            'shipping_needs_setup'    => false,
            'payment_needs_setup'     => false,
            'login_required'          => true,
            'block_only_gateways'     => [],
            'express_pay_has_buttons' => true,
            'plugin_spot_states'      => [],
            'hooked'                  => array_keys( WcHooks::HOOKS ),
        ];

        foreach ( array_column( $available, 'slug' ) as $slug ) {
            $layout           = new Layout();
            $layout->content  = LayoutTemplateCatalogue::markup( $slug );
            $layout->design   = LayoutTemplateCatalogue::design( $slug );
            $layout->template = $slug;

            $errors = array_filter(
                LayoutCheck::run( $layout, LayoutCheckFacts::logged_out_markup( $layout ), $facts ),
                static fn( array $finding ): bool => LayoutCheck::ERROR === $finding['severity']
            );

            $this->assertSame( [], array_column( $errors, 'code' ), $slug );
        }
    }

    public function test_free_lists_a_pro_template_locked(): void {
        $free = array_column( LayoutTemplateCatalogue::templates( false ), null, 'slug' );
        $this->assertTrue( $free[ LayoutTemplateCatalogue::DEFAULT_SLUG ]['available'] );
        $this->assertTrue( $free['multi-step-wizard']['pro'] );
        $this->assertFalse( $free['multi-step-wizard']['available'] );
        $this->assertTrue( array_column( LayoutTemplateCatalogue::templates( true ), 'available', 'slug' )['multi-step-wizard'] );
    }

    public function test_a_template_without_files_is_coming_soon(): void {
        $available = array_column( LayoutTemplateCatalogue::templates( true ), 'available', 'slug' );
        $this->assertFalse( $available['digital-product'] );

        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'This template is coming soon.' );
        LayoutTemplateCatalogue::assert_available( 'digital-product' );
    }
}
