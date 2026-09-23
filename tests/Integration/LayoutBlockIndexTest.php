<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Services\LayoutBlockIndex;

/** A layout must contain the Checkout block, but it may sit anywhere in the tree. */
class LayoutBlockIndexTest extends WP_UnitTestCase {

    public function test_accepts_checkout_nested_after_other_blocks(): void {
        $markup = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->'
            . '<!-- wp:group --><div class="wp-block-group">'
            . '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/slot {"name":"promo"} /--><!-- /wp:yaycheckout/checkout -->'
            . '</div><!-- /wp:group -->';

        $index = LayoutBlockIndex::from_markup( $markup );

        $this->assertIsArray( $index['slots'] );
    }

    public function test_rejects_layout_without_checkout(): void {
        $this->expectException( \InvalidArgumentException::class );

        LayoutBlockIndex::from_markup( '<!-- wp:group --><div class="wp-block-group"><!-- wp:yaycheckout/slot {"name":"promo"} /--></div><!-- /wp:group -->' );
    }

    public function test_extra_field_without_key_is_not_indexed(): void {
        $index = LayoutBlockIndex::from_markup(
            '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/extra-field /--><!-- wp:yaycheckout/extra-field {"fieldKey":"vat"} /--><!-- /wp:yaycheckout/checkout -->'
        );

        $this->assertSame( [ 'vat' ], array_keys( $index['extra_fields'] ) );
    }
}
