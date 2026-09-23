<?php
namespace YayCheckout\Tests\Support;

use YayCheckout\Models\Layout;

/** Fixtures the layout suites share. */
final class LayoutFactory {

    /** The smallest publishable markup: the root block alone, in the self-closing form core stores. */
    public static function checkout_markup(): string {
        return '<!-- wp:yaycheckout/checkout /-->';
    }

    /**
     * @param array $overrides wp_insert_post() fields.
     *
     * @throws \RuntimeException When the insert fails.
     */
    public static function create_draft( array $overrides = [] ): int {
        $id = wp_insert_post(
            array_merge(
                [
                    'post_type'    => Layout::POST_TYPE,
                    'post_status'  => 'draft',
                    'post_title'   => 'Test layout',
                    'post_content' => self::checkout_markup(),
                ],
                $overrides
            ),
            true
        );

        if ( is_wp_error( $id ) ) {
            throw new \RuntimeException( $id->get_error_message() );
        }

        return (int) $id;
    }

    /** A shop manager (WooCommerce's manage_woocommerce role), made current. */
    public static function as_shop_manager(): int {
        $user_id = wp_insert_user(
            [
                'user_login' => 'shop_manager_' . wp_generate_password( 6, false ),
                'user_pass'  => wp_generate_password(),
                'role'       => 'shop_manager',
            ]
        );
        wp_set_current_user( $user_id );

        return (int) $user_id;
    }
}
