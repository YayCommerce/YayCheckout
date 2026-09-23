<?php
/**
 * Plugin Name: Fake Cased
 * Version: 1.0
 */

function fake_cased_posted_data( $data ) {
    return $data;
}

function fake_cased_register() {
    add_filter( 'woocommerce_checkout_posted_data', 'fake_cased_posted_data' );
}
