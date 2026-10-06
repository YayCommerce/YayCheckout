<?php
namespace YayCheckout\Pro\Services;

defined( 'ABSPATH' ) || exit;

/** Page data the Assignment drawer's pickers read; Pro only, as the drawer is. */
class AssignmentStoreData {

    /** Roles for the Assignment picker, read from WP so no list is re-declared. */
    public static function get(): array {
        return [
            // Slug => translated name; every registered role, Woo's and other plugins' included.
            'roles' => array_map( 'translate_user_role', wp_roles()->get_names() ),
        ];
    }
}
