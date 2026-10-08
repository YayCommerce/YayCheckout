<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Classes for a storefront button, so it picks up a block theme's button
 * styles the way WooCommerce's own buttons do.
 */
class ButtonClassHelper {

    /** $base plus the theme's button element class, when WooCommerce provides one. */
    public static function classes( string $base ): string {
        $theme_class = function_exists( 'wc_wp_theme_get_element_class_name' ) ? wc_wp_theme_get_element_class_name( 'button' ) : '';

        return $theme_class ? $base . ' ' . $theme_class : $base;
    }
}
