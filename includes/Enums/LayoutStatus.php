<?php
namespace YayCheckout\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * Product lifecycle of a layout — the only status vocabulary REST and the
 * SPA speak. How each value is stored is LayoutPostStatus's concern.
 *
 * A constants class rather than a native enum: the plugin supports PHP 7.4.
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
     * The Layouts home tab: everything the merchant works with, not archived.
     *
     * @return string[]
     */
    public static function listed(): array {
        return [ self::DRAFT, self::PENDING, self::SCHEDULED, self::PUBLISHED ];
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    public static function is_valid( $value ): bool {
        return in_array( $value, self::all(), true );
    }
}
