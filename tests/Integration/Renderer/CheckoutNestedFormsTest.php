<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Renderer\CheckoutNestedForms;
use YayCheckout\Tests\Support\LayoutFactory;

/** A form a shortcode or plugin prints inside the Checkout block no longer ends the checkout form, which would leave the fields after it out of the order. */
class CheckoutNestedFormsTest extends WP_UnitTestCase {

    public function tear_down(): void {
        LayoutFactory::forget_served_layout();
        parent::tear_down();
    }

    public function test_matched_pairs_become_divs_and_unmatched_tags_are_dropped(): void {
        $this->assertSame( '<div method="post" class="sw"><select name="currency"></select></div><input name="b">', CheckoutNestedForms::neutralize( '<FORM method="post" class="sw"><select name="currency"></select></Form ><input name="b">' ) );
        // An unclosed opener would otherwise leave a <div> open that swallows the column's own closer.
        $this->assertSame( '<div class="col"><select></select></div><div class="col2"></div>', CheckoutNestedForms::neutralize( '<div class="col"><form class="x"><select></select></div><div class="col2"></div>' ) );
        $this->assertSame( '<div>a</div><input name="b">', CheckoutNestedForms::neutralize( '<div>a</form></div><input name="b">' ) );
        $this->assertSame( '<div id="o"><div id="i"></div></div>', CheckoutNestedForms::neutralize( '<form id="o"><form id="i"></form></form>' ) );
    }

    public function test_template_and_text_are_untouched(): void {
        $template = '<template class="yaycheckout-login__template"><dialog><form method="post"><input name="username"></form></dialog></template>';
        $this->assertSame( $template, CheckoutNestedForms::neutralize( $template ) );
        $this->assertSame( '<select></select>' . $template, CheckoutNestedForms::neutralize( '<form class="sw"><select></select>' . $template ) );
        $this->assertSame( '<div class="sw"><script>var s="</form>";</script><!-- </form> --></div>', CheckoutNestedForms::neutralize( '<form class="sw"><script>var s="</form>";</script><!-- </form> --></form>' ) );
        $this->assertSame( '<div class="form-row"></div>', CheckoutNestedForms::neutralize( '<div class="form-row"></div>' ) );
    }

    public function test_fragments_are_neutralized_while_a_layout_is_served(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );

        $out = CheckoutNestedForms::get_instance()->fragments( [ '.woocommerce-checkout-payment' => '<div><form><input name="x"></form></div>' ] );

        $this->assertSame( '<div><div><input name="x"></div></div>', $out['.woocommerce-checkout-payment'] );
    }
}
