<?php
/**
 * Plugin Name: Fake Checkout Plugin
 * Version: 3.2.0
 */

class Fake_Checkout_Plugin {
    public static function locate_template( $template ) {
        return $template;
    }

    public function gateways( $gateways ) {
        return $gateways;
    }
}

/** Hooks a closure, a static method (array and string form) and an instance method — every callable shape the scan must resolve; two fields hooks prove dedupe. */
function fake_checkout_plugin_register() {
    add_filter(
        'woocommerce_checkout_fields',
        function ( $fields ) {
            return $fields;
        }
    );
    add_filter( 'woocommerce_billing_fields', 'Fake_Checkout_Plugin::locate_template' );
    add_filter( 'woocommerce_locate_template', [ 'Fake_Checkout_Plugin', 'locate_template' ] );
    add_filter( 'woocommerce_available_payment_gateways', [ new Fake_Checkout_Plugin(), 'gateways' ] );
}
