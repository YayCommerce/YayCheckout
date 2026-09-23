<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Renderer\CheckoutExtraFields;
use YayCheckout\Tests\Support\LayoutFactory;

/** The served layout's extra fields are validated, stored on the order, and shown back. */
class CheckoutExtraFieldsTest extends WP_UnitTestCase {

    private const LAYOUT = '<!-- wp:yaycheckout/checkout -->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"vat","label":"VAT","required":true} /-->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"size","label":"Size","type":"select","options":[{"label":"Small","value":"s"}]} /-->'
        . '<!-- /wp:yaycheckout/checkout -->';

    public function set_up(): void {
        parent::set_up();
        LayoutFactory::serve( self::LAYOUT );
    }

    public function tear_down(): void {
        $_POST = [];
        LayoutFactory::forget_served_layout();
        parent::tear_down();
    }

    public function test_validate_reports_required_and_invalid_by_label(): void {
        $_POST  = [ 'size' => 'huge' ];
        $errors = new \WP_Error();

        CheckoutExtraFields::get_instance()->validate( [], $errors );

        $this->assertSame( [ 'yaycheckout_extra_vat', 'yaycheckout_extra_size' ], $errors->get_error_codes() );
        $this->assertSame( 'VAT is a required field.', $errors->get_error_message( 'yaycheckout_extra_vat' ) );
        $this->assertSame( 'Size is not a valid selection.', $errors->get_error_message( 'yaycheckout_extra_size' ) );
    }

    public function test_create_order_stores_each_value_and_a_snapshot(): void {
        $_POST = [
            'vat'  => 'EU123',
            'size' => 's',
        ];
        $order = new \WC_Order();

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing WooCommerce's own hook.
        do_action( 'woocommerce_checkout_create_order', $order, [] );

        $this->assertSame( 'EU123', $order->get_meta( 'vat' ) );
        $this->assertSame( 's', $order->get_meta( 'size' ) );
        $this->assertSame(
            [
                [
                    'key'     => 'vat',
                    'label'   => 'VAT',
                    'type'    => 'text',
                    'value'   => 'EU123',
                    'display' => 'EU123',
                ],
                [
                    'key'     => 'size',
                    'label'   => 'Size',
                    'type'    => 'select',
                    'value'   => 's',
                    'display' => 'Small',
                ],
            ],
            $order->get_meta( CheckoutExtraFields::META_SNAPSHOT )
        );
    }

    public function test_invalid_submission_stores_nothing(): void {
        $_POST = [ 'size' => 'huge' ];
        $order = new \WC_Order();

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing WooCommerce's own hook.
        do_action( 'woocommerce_checkout_create_order', $order, [] );

        $this->assertSame( '', $order->get_meta( CheckoutExtraFields::META_SNAPSHOT ) );
    }

    public function test_order_screens_print_the_snapshot(): void {
        $order = new \WC_Order();
        $order->update_meta_data(
            CheckoutExtraFields::META_SNAPSHOT,
            [
                [
                    'key'     => 'vat',
                    'label'   => 'VAT <i>',
                    'value'   => 'EU123',
                    'display' => 'EU123',
                ],
            ]
        );

        ob_start();
        CheckoutExtraFields::get_instance()->print_admin( $order );
        $admin = ob_get_clean();
        ob_start();
        CheckoutExtraFields::get_instance()->print_customer( $order );
        $customer = ob_get_clean();

        $this->assertSame( '<div class="yaycheckout-order-extra-fields"><h3>Extra fields</h3><p><strong>VAT &lt;i&gt;:</strong> EU123</p></div>', $admin );
        $this->assertSame( '<div class="yaycheckout-order-extra-fields"><p><strong>VAT &lt;i&gt;:</strong> EU123</p></div>', $customer );
    }

    /** Saved once, before WooCommerce saves the new order; no second write after it. */
    public function test_only_create_order_persists(): void {
        global $wp_filter;

        $instance = CheckoutExtraFields::get_instance();
        $after    = isset( $wp_filter['woocommerce_checkout_update_order_meta'] ) ? $wp_filter['woocommerce_checkout_update_order_meta']->callbacks : [];
        $ours     = array_filter( array_merge( [], ...array_values( $after ) ), static fn( $hook ) => is_array( $hook['function'] ) && $instance === $hook['function'][0] );

        $this->assertSame( 10, has_action( 'woocommerce_checkout_create_order', [ $instance, 'persist' ] ) );
        $this->assertSame( [], $ours );
    }

    /** Every option value the checkout renders is one validation accepts. */
    public function test_rendered_options_are_the_accepted_options(): void {
        $options = '[{"label":"Small","value":"s"},{"label":"Extra Large","value":""},{"label":"","value":"xl"}]';
        $html    = do_blocks( '<!-- wp:yaycheckout/extra-field {"fieldKey":"size","label":"Size","type":"select","options":' . $options . '} /-->' );

        preg_match_all( '/<option value="([^"]*)"/', $html, $rendered );

        $this->assertSame( array_keys( \YayCheckout\Services\LayoutMarkupReader::options( json_decode( $options, true ) ) ), $rendered[1] );
    }

    /** A retried order drops the previous attempt's values before storing the new ones. */
    public function test_retry_replaces_previous_values(): void {
        $order = new \WC_Order();
        $order->update_meta_data( 'size', 's' );
        $order->update_meta_data(
            CheckoutExtraFields::META_SNAPSHOT,
            [
                [
                    'key'     => 'size',
                    'label'   => 'Size',
                    'type'    => 'select',
                    'value'   => 's',
                    'display' => 'Small',
                ],
            ]
        );
        $_POST = [ 'vat' => 'EU123' ];

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing WooCommerce's own hook.
        do_action( 'woocommerce_checkout_create_order', $order, [] );

        $this->assertSame( '', $order->get_meta( 'size' ) );
        $this->assertSame( 'EU123', $order->get_meta( 'vat' ) );
        $this->assertSame( [ 'vat' ], array_column( $order->get_meta( CheckoutExtraFields::META_SNAPSHOT ), 'key' ) );
    }
}
