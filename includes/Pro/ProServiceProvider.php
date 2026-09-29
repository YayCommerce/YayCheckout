<?php
namespace YayCheckout\Pro;

use YayCheckout\Pro\Controllers\DesignPresetRestController;
use YayCheckout\Pro\Controllers\LayoutExportRestController;
use YayCheckout\Pro\Controllers\LayoutImportRestController;
use YayCheckout\Pro\Controllers\LayoutRulesRestController;
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
    }

    /** Adds `store`: the assignment pickers' countries and roles. */
    public function admin_data( array $data ): array {
        $data['store'] = AssignmentStoreData::get();

        return $data;
    }

    /** Every Pro REST controller, so the Free build simply has no such route. */
    public function rest_endpoints(): void {
        DesignPresetRestController::get_instance();
        LayoutExportRestController::get_instance();
        LayoutImportRestController::get_instance();
        LayoutRulesRestController::get_instance();
    }
}
