<?php
/**
 * Plugin Name: Fake Mu
 * Version: 0.1
 */

function fake_mu_process() {}

function fake_mu_register() {
    add_action( 'woocommerce_checkout_process', 'fake_mu_process' );
}
