<?php
namespace YayCheckout\Helpers;

use YayCheckout\Engine\Admin\LayoutEditorEmbed;

defined( 'ABSPATH' ) || exit;

/** URLs into the admin SPA, for links printed outside it. */
class AdminUrlHelper {

    /** The SPA's wp-admin page; the admin shell registers it under this slug. */
    public const MENU_SLUG = 'yaycheckout';

    /**
     * The Layouts screen with `$id` open in the embedded editor, on its checkout, or on its thank-you or pay-order
     * page when $view is LayoutEditorEmbed::VIEW_THANK_YOU or VIEW_PAY_ORDER. Mirrors editLayoutPath() and withEditLayout() in apps/admin/src/lib/helpers/layout.helper.ts.
     */
    public static function edit_layout( int $id, string $view = '' ): string {
        $url = admin_url( 'admin.php?page=' . self::MENU_SLUG ) . '#/layouts?edit=' . $id;

        return in_array( $view, [ LayoutEditorEmbed::VIEW_THANK_YOU, LayoutEditorEmbed::VIEW_PAY_ORDER ], true ) ? $url . '&view=' . $view : $url;
    }
}
