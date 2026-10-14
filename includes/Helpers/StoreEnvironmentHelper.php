<?php
namespace YayCheckout\Helpers;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use YayCheckout\Renderer\CheckoutHealth;
use YayCheckout\Repositories\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The "Store" facts on the Compatibility page: checkout type, versions,
 * theme, safe mode and the problem, if any, that safe mode acts on.
 */
class StoreEnvironmentHelper {

    /**
     * @return array{store_type: string, woocommerce_version: string, wordpress_version: string, theme: array{name: string, version: string, is_block_theme: bool}, safe_mode: bool, checkout_problems: array<int, array{reason: string, detail: ?string, blocks: bool, layout_id: ?int, layout_title: ?string}>}
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
            'checkout_problems'   => CheckoutHealth::reported_problems(),
        ];
    }

    /** What store_type() returns when the Checkout block is the store's checkout. */
    public const STORE_TYPE_BLOCK = 'block';

    /** 'block' when the Checkout block is the store's checkout, 'classic' otherwise — including on a WooCommerce too old to ship the Blocks utility. */
    public static function store_type(): string {
        return class_exists( CartCheckoutUtils::class ) && CartCheckoutUtils::is_checkout_block_default() ? self::STORE_TYPE_BLOCK : 'classic';
    }
}
