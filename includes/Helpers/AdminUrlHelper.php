<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/** URLs into the admin SPA, for links printed outside it. */
class AdminUrlHelper {

    /** The SPA's wp-admin page; the admin shell registers it under this slug. */
    public const MENU_SLUG = 'yaycheckout';

    /** The Layouts screen with `$id` open in the embedded editor. */
    public static function edit_layout( int $id ): string {
        return admin_url( 'admin.php?page=' . self::MENU_SLUG ) . '#/layouts?edit=' . $id;
    }
}
