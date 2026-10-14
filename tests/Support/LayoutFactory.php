<?php
namespace YayCheckout\Tests\Support;

use YayCheckout\Models\Layout;

/** Fixtures the layout suites share. */
final class LayoutFactory {

    /** The smallest publishable markup: the root block with the two blocks publish_error() requires. */
    public static function checkout_markup(): string {
        return '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/email /--><!-- wp:yaycheckout/payment /--><!-- wp:yaycheckout/place-order /--><!-- /wp:yaycheckout/checkout -->';
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

    /**
     * Makes $markup the layout the checkout serves: published, active, and
     * CheckoutPage's per-request memo cleared so the next read resolves it.
     */
    public static function serve( string $markup ): int {
        $id = self::create_draft( [ 'post_content' => $markup ] );
        ( new \YayCheckout\Repositories\LayoutRepository() )->activate( $id );
        self::forget_served_layout();

        return $id;
    }

    /** Clears what CheckoutPage memoises per request (the layout, the rendered HTML); the singleton outlives a test. */
    public static function forget_served_layout(): void {
        $fresh_values = [
            'layout' => false,
            'html'   => '',
        ];

        foreach ( $fresh_values as $property => $fresh ) {
            $memo = new \ReflectionProperty( \YayCheckout\Renderer\CheckoutPage::class, $property );
            $memo->setAccessible( true );
            $memo->setValue( \YayCheckout\Renderer\CheckoutPage::get_instance(), $fresh );
        }
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
