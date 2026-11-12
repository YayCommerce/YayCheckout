<?php
namespace YayCheckout\Pro\Services;

defined( 'ABSPATH' ) || exit;

/** Page data the Assignment drawer's pickers read; Pro only, as the drawer is. */
class AssignmentStoreData {

    /** Roles and countries for the Assignment pickers, read from WP and WooCommerce so no list is re-declared. */
    public static function get(): array {
        return [
            // Slug => translated name; every registered role, Woo's and other plugins' included.
            'roles'     => array_map( 'translate_user_role', wp_roles()->get_names() ),
            // Code => name; WooCommerce writes some names as HTML entities (&#197;land Islands), which React would print as typed.
            'countries' => array_map( static fn( $name ) => html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ), WC()->countries->get_countries() ),
        ];
    }
}
