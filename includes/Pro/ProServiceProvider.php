<?php
namespace YayCheckout\Pro;

use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/**
 * Entry point for everything Pro. Absent from the Free build.
 *
 * THE BOUNDARY RULE
 * -----------------
 * No file outside includes/Pro/ may name a YayCheckout\Pro\* class.
 * This class is bootstrapped by one guarded require in YayCheckout.php, which
 * is the single permitted reference; scripts/check-pro-boundary.sh fails
 * the build if a second one appears.
 *
 * That rule is what makes the directory genuinely removable rather than merely
 * tidy. Pro behaviour therefore attaches to core through hooks and filters
 * — as DerivedCacheBuilder::FILTER_CONTRIBUTORS was designed for — and never by
 * core calling into Pro code.
 */
class ProServiceProvider {

    /**
     * Mark the plan first: everything downstream reads Plan::is_pro(), so it
     * must be settled before any other component initialises.
     */
    public function boot(): void {
        Plan::mark_pro();

        /*
         * Pro features register here, via add_action/add_filter only.
         * The layout cap is core behaviour reading Plan, so it needs nothing
         * on this side.
         */

        /*
         * Several layouts may be active at once under Pro. Core's default
         * replaces the set with the layout being activated; merging is the
         * whole of the Pro difference.
         */
        add_filter(
            LayoutRepository::FILTER_ACTIVE_IDS,
            static function ( $ids, $id, $previous ) {
                return array_merge( (array) $previous, [ $id ] );
            },
            10,
            3
        );
    }
}
