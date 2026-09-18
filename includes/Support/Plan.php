<?php
namespace YayCheckout\Support;

use YayCheckout\Repositories\LayoutRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Which edition is installed. Derived, not declared: is_pro() reports whether
 * the Pro provider actually booted, so it cannot claim Pro while the files are
 * absent. Plan, not license — an expired Pro license is still the Pro plan.
 */
final class Plan {

    /** Same convention as wp_revisions_to_keep. */
    public const UNLIMITED = -1;

    private static bool $pro = false;

    /** Only ProServiceProvider::boot() may call this. */
    public static function mark_pro(): void {
        self::$pro = true;
    }

    public static function is_pro(): bool {
        return self::$pro;
    }

    /**
     * How many layouts this edition may hold.
     *
     * @return int Count, or self::UNLIMITED.
     */
    public static function layout_limit(): int {
        return self::is_pro() ? self::UNLIMITED : LayoutRepository::FREE_LIMIT;
    }

    /** Whether a layout count has used up this edition's allowance. Shared by create and duplicate so the two paths cannot drift. */
    public static function layout_limit_reached( int $count ): bool {
        $limit = self::layout_limit();

        return self::UNLIMITED !== $limit && $count >= $limit;
    }

    /** What a merchant is told when they hit the limit. */
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
