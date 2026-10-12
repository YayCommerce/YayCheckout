<?php
namespace YayCheckout\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * The one status vocabulary: WordPress post_status values, spoken by storage,
 * REST and the SPA alike. Constants, not a native enum: PHP 7.4 support.
 */
final class LayoutStatus {

    public const DRAFT     = 'draft';
    public const PENDING   = 'pending';
    public const SCHEDULED = 'future';

    public const PUBLISHED = 'publish';

    /** Archived layouts are kept: LayoutTrashSync exempts them from WP's scheduled trash purge. */
    public const ARCHIVED = 'trash';

    /**
     * @return string[]
     */
    public static function all(): array {
        return [ self::DRAFT, self::PENDING, self::SCHEDULED, self::PUBLISHED, self::ARCHIVED ];
    }

    /**
     * The Layouts home tab: everything not archived.
     *
     * @return string[]
     */
    public static function listed(): array {
        return [ self::DRAFT, self::PENDING, self::SCHEDULED, self::PUBLISHED ];
    }

    /**
     * Statuses shoppers may see: the ones the publish rules guard and an active layout may hold.
     * packages/blocks/src/checkout/publish-guard.ts copies it as LIVE_STATUSES.
     *
     * @return string[]
     */
    public static function live(): array {
        return [ self::PUBLISHED, self::SCHEDULED ];
    }

    public static function is_valid( $value ): bool {
        return in_array( $value, self::all(), true );
    }
}
