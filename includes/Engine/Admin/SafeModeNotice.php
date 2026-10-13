<?php
namespace YayCheckout\Engine\Admin;

use YayCheckout\Helpers\AdminUrlHelper;
use YayCheckout\PostTypes\LayoutPostType;
use YayCheckout\Renderer\CheckoutHealth;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Warns store managers on every admin screen while the checkout layout has a problem: with safe
 * mode on, shoppers are getting WooCommerce's checkout; with it off, the layout may not work.
 */
class SafeModeNotice {

    use SingletonTrait;

    protected function __construct() {
        add_action( 'admin_notices', [ $this, 'print_notice' ] );
    }

    public function print_notice(): void {
        if ( ! current_user_can( LayoutPostType::CAPABILITY ) ) {
            return;
        }

        $problem = CheckoutHealth::reported_problem();
        if ( '' === $problem ) {
            return;
        }

        $message = ( new SettingsRepository() )->is_safe_mode()
            /* translators: %s: why the layout is not served. */
            ? __( 'Shoppers are getting the WooCommerce checkout: %s', 'yaycheckout' )
            /* translators: %s: why the layout may break checkout. */
            : __( 'Your checkout layout may not work: %s', 'yaycheckout' );

        printf(
            '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
            esc_html( sprintf( $message, $problem ) ),
            esc_url( admin_url( 'admin.php?page=' . AdminUrlHelper::MENU_SLUG ) . '#/compatibility' ),
            esc_html__( 'See Compatibility', 'yaycheckout' )
        );
    }
}
