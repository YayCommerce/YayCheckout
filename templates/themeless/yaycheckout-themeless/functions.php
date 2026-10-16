<?php
/**
 * The supports the site's theme would otherwise declare for the checkout: a
 * <title> from wp_head(), and HTML5 markup for WooCommerce's forms and scripts.
 */

defined( 'ABSPATH' ) || exit;

add_action(
    'after_setup_theme',
    function () {
        add_theme_support( 'title-tag' );
        add_theme_support( 'html5', [ 'script', 'style', 'search-form', 'gallery', 'caption' ] );
    }
);
