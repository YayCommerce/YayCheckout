<?php
namespace YayCheckout\Integrations;

use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Fixes for YayPricing and YayPricing Lite, active only while their scripts are enqueued.
 *
 * Its unit price starts with "2 ×", which the Items block's "each" then contradicts. Pro also refreshes
 * its "spend more to get…" notice on cart-page events, never on updated_checkout, so the Items block's
 * stepper, remove link and Coupon form left it stale. Lite has no such notice.
 */
class YayPricing {

    use SingletonTrait;

    /** Both editions enqueue it on every page. */
    private const BASE_SCRIPT = 'yaydp-frontend-index';

    /** Pro only. */
    private const NOTICE_SCRIPT = 'yaydp-frontend-encouraged-notice';

    /**
     * YayPricing's refresh listens for the cart-page event updated_cart_totals. updated_checkout also fires on page
     * load and on every address, shipping or payment change, so fire it only when the cart itself changed:
     * its subtotal and coupon state (#yaycheckout-visibility-state) or an Items stepper quantity.
     */
    private const REFRESH_JS = "( function ( $ ) { var last = null; $( document.body ).on( 'updated_checkout', function () { var el = document.getElementById( 'yaycheckout-visibility-state' ), now = ( el ? el.textContent : '' ) + $( '.yaycheckout-qty__input' ).map( function () { return this.value; } ).get().join(); if ( null !== last && now !== last ) { $( document.body ).trigger( 'updated_cart_totals' ); } last = now; } ); } )( jQuery );";

    private const UNIT_PRICE_CSS = '.yaycheckout-order-summary-items .yaydp-cart-item-quantity { display: none; }';

    protected function __construct() {
        // After YayPricing enqueues its scripts at 10 and CheckoutPage enqueues checkout-base at 20.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 21 );
    }

    public function enqueue(): void {
        if ( ! wp_script_is( self::BASE_SCRIPT, 'enqueued' ) || ! CheckoutPage::get_instance()->takes_over() ) {
            return;
        }

        if ( wp_script_is( self::NOTICE_SCRIPT, 'enqueued' ) ) {
            wp_add_inline_script( self::NOTICE_SCRIPT, self::REFRESH_JS );
        }

        wp_add_inline_style( generate_block_asset_handle( 'yaycheckout/order-summary-items', 'style' ), self::UNIT_PRICE_CSS );
    }
}
