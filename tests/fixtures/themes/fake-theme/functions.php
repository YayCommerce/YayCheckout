<?php
function fake_theme_fields( $fields ) {
    return $fields;
}

function fake_theme_register() {
    add_filter( 'woocommerce_billing_fields', 'fake_theme_fields' );
}
