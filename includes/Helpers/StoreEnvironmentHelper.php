<?php
namespace YayCheckout\Helpers;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use YayCheckout\Repositories\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The "Store" facts on the Compatibility page: checkout type, versions,
 * theme and whether safe mode is serving WooCommerce's stock checkout.
 */
class StoreEnvironmentHelper {

    /**
     * @return array{store_type: string, woocommerce_version: string, wordpress_version: string, theme: array{name: string, version: string, is_block_theme: bool}, safe_mode: bool}
     */
    public static function describe(): array {
        $theme = wp_get_theme();

        return [
            'store_type'          => self::store_type(),
            'woocommerce_version' => WC()->version,
            'wordpress_version'   => get_bloginfo( 'version' ),
            'theme'               => [
                'name'           => (string) $theme->get( 'Name' ),
                'version'        => (string) $theme->get( 'Version' ),
                'is_block_theme' => wp_is_block_theme(),
            ],
            'safe_mode'           => ! empty( ( new SettingsRepository() )->get()->general['safe_mode'] ),
        ];
    }

    /** 'block' when the Checkout block is the store's checkout, 'classic' otherwise — including on a WooCommerce too old to ship the Blocks utility. */
    private static function store_type(): string {
        return class_exists( CartCheckoutUtils::class ) && CartCheckoutUtils::is_checkout_block_default() ? 'block' : 'classic';
    }
}
