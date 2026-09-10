<?php
namespace YayCheckout\Support;

use YayCheckout\Repositories\LayoutRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Which edition of the plugin is installed.
 *
 * The answer is derived, never declared: is_pro() reports whether the pro
 * service provider actually booted. A build-time constant could disagree with
 * what shipped — say "pro" while the Pro files are absent — and the first
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

    /**
     * Whether an existing layout count has used up this edition's allowance.
     *
     * The rule lives here so creation and duplication cannot drift apart —
     * they enforce the same limit through different code paths.
     *
     * @param  int $count Layouts that already exist.
     * @return bool
     */
    public static function layout_limit_reached( int $count ): bool {
        $limit = self::layout_limit();

        return self::UNLIMITED !== $limit && $count >= $limit;
    }

    /**
     * What a merchant is told when they hit the limit.
     *
     * Shared so translators see one string rather than two identical ones,
     * and so the upgrade wording is changed in a single place.
     */
    public static function layout_limit_message(): string {
        $limit = self::layout_limit();

        return sprintf(
            /* translators: %d: number of layouts allowed on the current plan. */
            _n(
                'Your plan includes %d checkout layout. Upgrade to Pro for unlimited layouts.',
                'Your plan includes %d checkout layouts. Upgrade to Pro for unlimited layouts.',
                $limit,
                'yaycheckout'
            ),
            $limit
        );
    }
}
