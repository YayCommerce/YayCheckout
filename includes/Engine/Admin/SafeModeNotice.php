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

        $problems = CheckoutHealth::reported_problems();
        if ( [] === $problems ) {
            return;
        }

        // The first one: the Compatibility page lists the rest.
        $problem = $problems[0];
        $more    = count( $problems ) - 1;

        // A one-off render failure doesn't hide the layout, so only a blocking problem says shoppers are on WooCommerce's.
        $message = ( new SettingsRepository() )->is_safe_mode() && $problem['blocks']
            /* translators: %s: why the layout is not served. */
            ? __( 'Shoppers are getting the WooCommerce checkout: %s', 'yaycheckout' )
            /* translators: %s: why the layout may break checkout. */
            : __( 'Your checkout layout may not work: %s', 'yaycheckout' );

        $edit = null === $problem['layout_id']
            ? ''
            : sprintf(
                '<a href="%s">%s</a> ',
                esc_url( AdminUrlHelper::edit_layout( $problem['layout_id'] ) ),
                '' !== $problem['layout_title']
                    /* translators: %s: layout name. */
                    ? esc_html( sprintf( __( 'Edit %s', 'yaycheckout' ), $problem['layout_title'] ) )
                    : esc_html__( 'Edit layout', 'yaycheckout' )
            );

        $others = 0 === $more
            ? ''
            /* translators: %d: number of other problems. */
            : esc_html( sprintf( _n( 'and %d more problem.', 'and %d more problems.', $more, 'yaycheckout' ), $more ) ) . ' ';

        printf(
            '<div class="notice notice-warning"><p>%s %s%s<a href="%s">%s</a></p></div>',
            esc_html( sprintf( $message, $problem['reason'] ) ),
            $edit, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from escaped parts.
            $others, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from escaped parts.
            esc_url( admin_url( 'admin.php?page=' . AdminUrlHelper::MENU_SLUG ) . '#/compatibility' ),
            esc_html__( 'See Compatibility', 'yaycheckout' )
        );
    }
}
