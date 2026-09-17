<?php
namespace YayCheckout\Renderer;

use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Force-enqueue the classic checkout scripts on the checkout page.
 *
 * WooCommerce decides per request whether wc-checkout and friends are needed
 * and skips them on block checkouts and some themes. A layout that relies on
 * them then breaks silently. The advanced.force_enqueue_checkout_scripts
 * setting is the support escape hatch: when on, they load regardless.
 */
class CheckoutScripts {
    use SingletonTrait;

    /** After WC_Frontend_Scripts registers its handles at the default priority. */
    private const PRIORITY = 20;

    protected function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_force_enqueue' ], self::PRIORITY );
    }

    public function maybe_force_enqueue(): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

        if ( empty( ( new SettingsRepository() )->get()->advanced['force_enqueue_checkout_scripts'] ) ) {
            return;
        }

        wp_enqueue_script( 'wc-checkout' );
        wp_enqueue_script( 'wc-country-select' );
        wp_enqueue_script( 'wc-address-i18n' );
    }
}
