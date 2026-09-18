<?php
namespace YayCheckout\Pro;

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

        // Pro features register here via add_action/add_filter. Empty in 1.x.
    }
}
