<?php
namespace YayCheckout\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * The only status vocabulary REST and the SPA speak. Constants, not a native
 * enum: PHP 7.4 support.
 */
final class LayoutStatus {

    public const DRAFT     = 'draft';
    public const PENDING   = 'pending';
    public const SCHEDULED = 'scheduled';
    public const PUBLISHED = 'published';
    public const ARCHIVED  = 'archived';

    /**
     * @return string[]
     */
    public static function all(): array {
        return [ self::DRAFT, self::PENDING, self::SCHEDULED, self::PUBLISHED, self::ARCHIVED ];
    }

    /**
     * @return string[]
     */
    public static function listed(): array {
        return [ self::DRAFT, self::PENDING, self::SCHEDULED, self::PUBLISHED ];
    }

    public static function is_valid( $value ): bool {
        return in_array( $value, self::all(), true );
    }
}
