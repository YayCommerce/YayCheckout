<?php
namespace YayCheckout\Support;

use YayCheckout\Repositories\LayoutRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Which edition of the plugin is installed.
 *
 * The answer is derived, never declared: is_pro() reports whether the pro
 * service provider actually booted. A build-time constant could disagree with
 * what shipped — say "pro" while the pro files are absent — and the first
 * pro call would then fatal. A derived flag cannot lie, because the thing
 * it reports is the thing that happened.
 *
 * This is *plan*, not license. An expired Pro license is still the Pro plan;
 * license state is a separate, runtime concern.
 */
final class Plan {

    /** Unlimited, matching the wp_revisions_to_keep convention. */
    public const UNLIMITED = -1;

    private static bool $pro = false;

    /**
     * Called by ProServiceProvider::boot(), and by nothing else.
     */
    public static function mark_pro(): void {
        self::$pro = true;
    }

    public static function is_pro(): bool {
        return self::$pro;
    }

    /**
     * How many layouts this edition may hold.
     *
     * @return int Layout count, or self::UNLIMITED.
     */
    public static function layout_limit(): int {
        return self::is_pro() ? self::UNLIMITED : LayoutRepository::FREE_LIMIT;
    }
}
