<?php
namespace YayCheckout\Premium;

use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/**
 * Entry point for everything premium. Absent from the Free build.
 *
 * THE BOUNDARY RULE
 * -----------------
 * No file outside includes/Premium/ may name a YayCheckout\Premium\* class.
 * This class is bootstrapped by one guarded require in YayCheckout.php, which
 * is the single permitted reference; scripts/check-premium-boundary.sh fails
 * the build if a second one appears.
 *
 * That rule is what makes the directory genuinely removable rather than merely
 * tidy. Premium behaviour therefore attaches to core through hooks and filters
 * — as DerivedCacheBuilder::FILTER_CONTRIBUTORS was designed for — and never by
 * core calling into premium code.
 */
class PremiumServiceProvider {

    /**
     * Mark the plan first: everything downstream reads Plan::is_pro(), so it
     * must be settled before any other component initialises.
     */
    public function boot(): void {
        Plan::mark_pro();

        /*
         * Premium features register here, via add_action/add_filter only.
         * Empty in 1.x — the layout cap is core behaviour reading Plan, so it
         * needs nothing on this side.
         */
    }
}
