<?php
namespace YayCheckout\Pro;

use YayCheckout\Pro\Controllers\LayoutExportRestController;
use YayCheckout\Pro\Controllers\LayoutImportRestController;
use YayCheckout\Pro\Controllers\LayoutRulesRestController;
use YayCheckout\Pro\Controllers\PayOrderRestController;
use YayCheckout\Pro\Controllers\ThankYouRestController;
use YayCheckout\Pro\Renderer\PayOrderPage;
use YayCheckout\Pro\Renderer\ThankYouPage;
use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Pro\Services\AssignmentStoreData;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/**
 * Entry point for everything Pro; absent from the Free build. Nothing outside
 * includes/Pro/ may name a YayCheckout\Pro\* class (scripts/check-pro-boundary.sh
 * enforces it), so Pro attaches through hooks and filters only.
 */
class ProServiceProvider {

    public function boot(): void {
        // Must be first: everything downstream reads Plan::is_pro().
        Plan::mark_pro();

        add_action( 'rest_api_init', [ $this, 'rest_endpoints' ] );
        add_filter( 'yaycheckout_localize_admin_data', [ $this, 'admin_data' ] );
        add_filter( 'yaycheckout_built_blocks', [ $this, 'built_blocks' ] );

        // The thank-you and pay-order pages: order-received and order-pay takeover, their editor views, admin bar item and live values.
        ThankYouPage::get_instance();
        PayOrderPage::get_instance();
    }

    /**
     * Adds the Pro blocks (the step blocks and the thank-you and pay-order pages' order blocks), built under assets/dist/blocks/pro/, which the Free zip leaves out.
     *
     * @param array<string, string> $built Block directory name => path.
     * @return array<string, string>
     */
    public function built_blocks( array $built ): array {
        return array_merge( $built, BlockRegistry::built_dirs( 'assets/dist/blocks/pro/*/block.json' ) );
    }

    /** Adds `store`: the roles the assignment picker lists. */
    public function admin_data( array $data ): array {
        $data['store'] = AssignmentStoreData::get();

        return $data;
    }

    /** Every Pro REST controller, so the Free build simply has no such route. */
    public function rest_endpoints(): void {
        LayoutExportRestController::get_instance();
        LayoutImportRestController::get_instance();
        LayoutRulesRestController::get_instance();
        ThankYouRestController::get_instance();
        PayOrderRestController::get_instance();
    }
}
