<?php
namespace YayCheckout\Helpers;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The "Store" facts on the Compatibility page: checkout type, versions,
 * theme, whether safe mode is serving WooCommerce's stock checkout, and how
 * many layouts are active (none leaves the theme's checkout in place).
 */
class StoreEnvironmentHelper {

    /**
     * @return array{store_type: string, woocommerce_version: string, wordpress_version: string, theme: array{name: string, version: string, is_block_theme: bool}, safe_mode: bool, active_layouts: int}
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
            'safe_mode'           => ( new SettingsRepository() )->is_safe_mode(),
            'active_layouts'      => ( new LayoutRepository() )->active_count(),
        ];
    }

    /** 'block' when the Checkout block is the store's checkout, 'classic' otherwise — including on a WooCommerce too old to ship the Blocks utility. */
    private static function store_type(): string {
        return class_exists( CartCheckoutUtils::class ) && CartCheckoutUtils::is_checkout_block_default() ? 'block' : 'classic';
    }
}
