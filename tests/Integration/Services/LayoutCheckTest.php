<?php
namespace YayCheckout\Tests\Integration\Services;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Services\LayoutCheck;

/** Run test's static rules: what stops a shopper ordering is an error, what may stop them is a warning. */
class LayoutCheckTest extends WP_UnitTestCase {

    /** A store with everything set up: a Terms page, shipping, a gateway, guest checkout on. */
    private const FACTS = [
        'locked_blocks'           => [],
        'terms_page'              => true,
        'ships'                   => true,
        'shipping_needs_setup'    => false,
        'payment_needs_setup'     => false,
        'login_required'          => false,
        'block_only_gateways'     => [],
        'express_pay_has_buttons' => true,
        'plugin_spot_states'      => [],
        'hooked'                  => [],
    ];

    private const CLEAN = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/email /--><!-- wp:yaycheckout/billing /--><!-- wp:yaycheckout/shipping-methods /--><!-- wp:yaycheckout/payment /--><!-- wp:yaycheckout/terms /--><!-- wp:yaycheckout/place-order /--><!-- /wp:yaycheckout/checkout -->';

    private const LOGGED_OUT_WITH_LOGIN = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/login /--><!-- /wp:yaycheckout/checkout -->';

    private const PLACE_ORDER_IN_STEP_ONE = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/email /--><!-- wp:yaycheckout/shipping-methods /--><!-- wp:yaycheckout/payment /--><!-- wp:yaycheckout/terms /-->'
        . '<!-- wp:yaycheckout/steps --><!-- wp:yaycheckout/step --><!-- wp:yaycheckout/place-order /--><!-- wp:yaycheckout/step-button /--><!-- /wp:yaycheckout/step -->'
        . '<!-- wp:yaycheckout/step --><!-- wp:yaycheckout/billing /--><!-- /wp:yaycheckout/step --><!-- /wp:yaycheckout/steps -->'
        . '<!-- /wp:yaycheckout/checkout -->';

    private const HIDDEN_REQUIRED = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/email /--><!-- wp:yaycheckout/billing /--><!-- wp:yaycheckout/shipping-methods /--><!-- wp:yaycheckout/payment /-->'
        . '<!-- wp:group {"yaycheckoutVisibility":{"action":"hide","rules":[{"type":"subtotal","op":"lt","amount":1000}]}} --><div class="wp-block-group">'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"gift_note","required":true} /-->'
        . '</div><!-- /wp:group -->'
        . '<!-- wp:yaycheckout/terms /--><!-- wp:yaycheckout/place-order /--><!-- /wp:yaycheckout/checkout -->';

    public function test_missing_terms_is_an_error_when_a_terms_page_is_set(): void {
        $codes = $this->codes( self::without( 'yaycheckout/terms' ), [ 'terms_page' => true ] );
        $this->assertContains( 'missing_terms', $codes['error'] );
        $this->assertNotContains( 'missing_terms', $this->codes( self::without( 'yaycheckout/terms' ), [ 'terms_page' => false ] )['error'] );
    }

    public function test_place_order_outside_the_last_step_is_an_error(): void {
        $codes = $this->codes( self::PLACE_ORDER_IN_STEP_ONE );
        $this->assertContains( 'place_order_not_in_last_step', $codes['error'] );
        $this->assertNotContains( 'steps_without_next_button', $codes['error'] );
    }

    public function test_required_field_under_a_visibility_setting_is_a_warning(): void {
        $findings = LayoutCheck::run( $this->layout( self::HIDDEN_REQUIRED ), '', self::FACTS );
        $hit      = array_values( array_filter( $findings, static fn( $f ) => 'hidden_required_field' === $f['code'] ) );
        $this->assertCount( 1, $hit );
        $this->assertSame( 'warning', $hit[0]['severity'] );
        $this->assertSame(
            [
                'view'  => 'main',
                'name'  => 'yaycheckout/extra-field',
                'index' => 0,
            ],
            $hit[0]['block']
        );
    }

    public function test_a_field_hidden_only_while_itself_is_empty_is_not_flagged(): void {
        // The templates' State column: shown while the State field isn't empty, which it is only while the
        // country's locale hides it, and WooCommerce then doesn't require it.
        $self   = '{"action":"show","rules":[{"type":"block","block":"bilstate","is":"not_empty"}]}';
        $mixed  = '{"action":"show","rules":[{"type":"block","block":"bilstate","is":"not_empty"},{"type":"subtotal","op":"gte","amount":50}]}';
        $column = static fn( string $setting ): string => str_replace(
            '<!-- wp:yaycheckout/place-order /-->',
            '<!-- wp:column {"yaycheckoutVisibility":' . $setting . '} --><div class="wp-block-column">'
                . '<!-- wp:yaycheckout/field {"blockId":"bilstate","fieldKey":"billing_state","type":"state","label":"State","required":true} /-->'
                . '</div><!-- /wp:column --><!-- wp:yaycheckout/place-order /-->',
            self::CLEAN
        );

        $this->assertNotContains( 'hidden_required_field', $this->codes( $column( $self ) )['warning'] );
        // Rules are ANDed: the subtotal rule can hide the column while State still needs a value.
        $this->assertContains( 'hidden_required_field', $this->codes( $column( $mixed ) )['warning'] );
    }

    public function test_a_clean_layout_has_no_findings(): void {
        $this->assertSame( [], LayoutCheck::run( $this->layout( self::CLEAN ), self::LOGGED_OUT_WITH_LOGIN, self::FACTS ) );
    }

    private static function without( string $block ): string {
        return str_replace( '<!-- wp:' . $block . ' /-->', '', self::CLEAN );
    }

    private function layout( string $markup ): Layout {
        $layout          = new Layout();
        $layout->content = $markup;
        $layout->design  = LayoutDesign::from_meta( [] );

        return $layout;
    }

    /** @return array{error: string[], warning: string[]} The codes found, by severity. */
    private function codes( string $markup, array $facts = [] ): array {
        $codes = [
            'error'   => [],
            'warning' => [],
        ];

        foreach ( LayoutCheck::run( $this->layout( $markup ), self::LOGGED_OUT_WITH_LOGIN, array_merge( self::FACTS, $facts ) ) as $finding ) {
            $codes[ $finding['severity'] ][] = $finding['code'];
        }

        return $codes;
    }
}
