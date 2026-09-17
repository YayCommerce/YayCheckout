<?php
namespace YayCheckout\Renderer;

use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Support escape hatch: WooCommerce skips wc-checkout on block checkouts and
 * some themes, and a layout relying on it then breaks silently.
 */
class CheckoutScripts {
    use SingletonTrait;

    /** After WC_Frontend_Scripts registers its handles at priority 10. */
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
