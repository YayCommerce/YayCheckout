<?php
namespace YayCheckout\Pro\Services;

defined( 'ABSPATH' ) || exit;

/** Page data the Assignment drawer's pickers read; Pro only, as the drawer is. */
class AssignmentStoreData {

    /**
     * Countries, roles and price format for the Assignment pickers, read from Woo and WP so no list is re-declared.
     */
    public static function get(): array {
        return [
            // Code => name, locale-sorted: the list Woo's own country selects show. Some names
            // arrive as HTML entities (S&atilde;o Tom&eacute;); React prints those literally.
            'countries' => array_map(
                static function ( $name ) {
                    return html_entity_decode( $name, ENT_QUOTES, 'UTF-8' );
                },
                WC()->countries->get_countries()
            ),
            // Slug => translated name; every registered role, Woo's and other plugins' included.
            'roles'     => array_map( 'translate_user_role', wp_roles()->get_names() ),
            // Store currency format, so amounts are typed the way Woo prints prices.
            'currency'  => [
                'symbol'             => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
                'position'           => get_option( 'woocommerce_currency_pos', 'left' ),
                'decimals'           => wc_get_price_decimals(),
                'decimal_separator'  => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
            ],
        ];
    }
}
