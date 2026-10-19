<?php
namespace YayCheckout\Integrations;

use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Fixes for YayPricing, active only while its encouraged-notice script is enqueued.
 *
 * YayPricing refreshes its "spend more to get…" notice on cart-page events, never on updated_checkout,
 * so the Items block's stepper, remove link and Coupon form left it stale. Its unit price also starts
 * with "2 ×", which the Items block's "each" then contradicts.
 */
class YayPricing {

    use SingletonTrait;

    private const NOTICE_SCRIPT = 'yaydp-frontend-encouraged-notice';

    /** YayPricing's refresh listens for this cart-page event; updated_checkout runs after every fragment swap. */
    private const REFRESH_JS = "jQuery( document.body ).on( 'updated_checkout', function () { jQuery( document.body ).trigger( 'updated_cart_totals' ); } );";

    private const UNIT_PRICE_CSS = '.yaycheckout-order-summary-items .yaydp-cart-item-quantity { display: none; }';

    protected function __construct() {
        // After YayPricing enqueues its scripts at 10 and CheckoutPage enqueues checkout-base at 20.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 21 );
    }

    public function enqueue(): void {
        if ( ! wp_script_is( self::NOTICE_SCRIPT, 'enqueued' ) || ! CheckoutPage::get_instance()->takes_over() ) {
            return;
        }

        wp_add_inline_script( self::NOTICE_SCRIPT, self::REFRESH_JS );
        wp_add_inline_style( generate_block_asset_handle( 'yaycheckout/order-summary-items', 'style' ), self::UNIT_PRICE_CSS );
    }
}
