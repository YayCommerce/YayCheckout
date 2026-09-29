<?php
namespace YayCheckout\Pro\Services;

defined( 'ABSPATH' ) || exit;

/** Page data the Assignment drawer's pickers read; Pro only, as the drawer is. */
class AssignmentStoreData {

    /**
     * Countries and roles for the Assignment pickers, read from Woo and WP so no list is re-declared.
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
        ];
    }
}
