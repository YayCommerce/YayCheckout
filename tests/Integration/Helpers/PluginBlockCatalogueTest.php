<?php
namespace YayCheckout\Tests\Integration\Helpers;

use WP_UnitTestCase;
use YayCheckout\Helpers\PluginBlockCatalogue;

/** PluginBlockCatalogue::spots_in() decides where YayBoost's own checkout output is removed: a wrong spot silently hides a shopper's order bump. */
class PluginBlockCatalogueTest extends WP_UnitTestCase {

    public function tear_down(): void {
        if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'yayboost/order-bump' ) ) {
            unregister_block_type( 'yayboost/order-bump' );
        }

        parent::tear_down();
    }

    public function test_spots_in(): void {
        $placeholder = '<!-- wp:group --><div class="wp-block-group"><!-- wp:yaycheckout/plugin-block {"target":"yayboost/free-shipping-bar"} /--></div><!-- /wp:group -->';
        $real_bump   = '<!-- wp:yayboost/order-bump /-->';
        $unknown     = '<!-- wp:yaycheckout/plugin-block {"target":"acme/thing"} /-->';

        // A nested placeholder counts; a target outside the catalogue doesn't.
        $this->assertSame( [ 'yayboost/free-shipping-bar' ], PluginBlockCatalogue::spots_in( $placeholder . $real_bump . $unknown ) );

        // YayBoost's own block is no spot even while registered: its hook output must stay, or the bump is lost.
        register_block_type( 'yayboost/order-bump', [ 'render_callback' => '__return_empty_string' ] );

        $this->assertSame( [ 'yayboost/free-shipping-bar' ], PluginBlockCatalogue::spots_in( $placeholder . $real_bump . $real_bump ) );
        $this->assertSame( [], PluginBlockCatalogue::spots_in( '' ) );
    }
}
