<?php
namespace YayCheckout\Helpers;

use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Controllers\LayoutTemplateCatalogue;
use YayCheckout\Models\Layout;
use YayCheckout\Renderer\CheckoutLogin;
use YayCheckout\Renderer\ExpressPay;
use YayCheckout\Renderer\WcHooks;
use YayCheckout\Repositories\CompatibilityCatalogueRepository;
use YayCheckout\Support\CompatibilityCatalogue;

defined( 'ABSPATH' ) || exit;

/**
 * What Services\LayoutCheck needs to know about this store, read once per Run test. It runs inside a REST
 * request, so, as with the Compatibility page's hook scan, a callback added only on `wp` or later is unseen.
 */
class LayoutCheckFacts {

    /** The hook every template places a Hook block for; WcHooks::core_callbacks() says which callbacks are WooCommerce's. */
    private const BEFORE_FORM_HOOK = 'woocommerce_before_checkout_form';

    /**
     * @return array{locked_blocks: string[], terms_page: bool, ships: bool, shipping_needs_setup: bool, payment_needs_setup: bool, login_required: bool, block_only_gateways: string[], express_pay_has_buttons: bool, plugin_spot_states: array<string, string>, before_form_hooked: bool, settings_urls: array{shipping: string, payment: string, terms: string}}
     */
    public static function current(): array {
        return [
            'locked_blocks'           => BlockRegistry::locked_blocks(),
            'terms_page'              => wc_terms_and_conditions_page_id() > 0,
            'ships'                   => wc_shipping_enabled(),
            'shipping_needs_setup'    => CheckoutSetupHelper::shipping_needs_setup(),
            'payment_needs_setup'     => CheckoutSetupHelper::payment_needs_setup(),
            'login_required'          => CheckoutLogin::login_required(),
            'block_only_gateways'     => self::block_only_gateways(),
            'express_pay_has_buttons' => ExpressPay::has_buttons(),
            'plugin_spot_states'      => array_column( PluginBlockCatalogue::editor_data()['entries'], 'state', 'target' ),
            'before_form_hooked'      => WcHooks::callbacks_besides( self::BEFORE_FORM_HOOK, WcHooks::core_callbacks( self::BEFORE_FORM_HOOK ) ) > 0,
            'settings_urls'           => [
                'shipping' => CheckoutSetupHelper::shipping_settings_url(),
                'payment'  => CheckoutSetupHelper::payment_settings_url(),
                'terms'    => admin_url( 'admin.php?page=wc-settings&tab=advanced' ),
            ],
        ];
    }

    /** What a guest who must log in sees: the layout's saved logged-out view, else its template's (or the shared one). */
    public static function logged_out_markup( Layout $layout ): string {
        return '' !== trim( $layout->logged_out_content ) ? $layout->logged_out_content : LayoutTemplateCatalogue::logged_out_markup( $layout->template );
    }

    /**
     * Titles of the payment methods that work only in WooCommerce's Checkout block, as the Compatibility page lists them.
     * A failed catalogue fetch falls back to the bundled one, and a failed detection reports none.
     *
     * @return string[]
     */
    private static function block_only_gateways(): array {
        try {
            $catalogue = ( new CompatibilityCatalogueRepository() )->get();
        } catch ( \Throwable $ex ) {
            $catalogue = CompatibilityCatalogueRepository::bundled();
        }

        try {
            $rows = GatewayCompatibilityHelper::list( $catalogue );
        } catch ( \Throwable $ex ) {
            Logger::exception( $ex, 'LayoutCheckFacts::block_only_gateways' );
            return [];
        }

        $block_only = array_filter( $rows, static fn( array $row ): bool => CompatibilityCatalogue::STATUS_BLOCK_ONLY === $row['status'] );

        return array_values( array_column( $block_only, 'title' ) );
    }
}
