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

    /** WP cron empties trash after EMPTY_TRASH_DAYS, so archive is not indefinite. */
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

    public static function is_valid( $value ): bool {
        return in_array( $value, self::all(), true );
    }
}
