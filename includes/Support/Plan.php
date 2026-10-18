<?php
namespace YayCheckout\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Which edition is installed. Derived, not declared: is_pro() reports whether
 * the Pro provider actually booted, so it cannot claim Pro while the files are
 * absent. Plan, not license — an expired Pro license is still the Pro plan.
 */
final class Plan {

    private static bool $pro = false;

    /** Only ProServiceProvider::boot() may call this. */
    public static function mark_pro(): void {
        self::$pro = true;
    }

    public static function is_pro(): bool {
        return self::$pro;
    }
}
