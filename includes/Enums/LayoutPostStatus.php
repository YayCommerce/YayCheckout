<?php
namespace YayCheckout\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress post_status values a layout post can hold. Storage detail of
 * LayoutRepository; never serialized to REST — see LayoutStatus for that.
 *
 * A constants class rather than a native enum: the plugin supports PHP 7.4.
 */
final class LayoutPostStatus {

    public const DRAFT = 'draft';

    /** Awaiting review before it can be published. Core's own status. */
    public const PENDING = 'pending';

    /** Publishes itself at post_date. Core's 'future' status. */
    public const SCHEDULED = 'future';

    /**
     * Published layouts are live.
     */
    public const PUBLISHED = 'publish';

    /**
     * Archived layouts sit in WordPress trash. Note WP cron empties trash
     * after EMPTY_TRASH_DAYS (30 by default), so archive is not indefinite.
     */
    public const ARCHIVED = 'trash';

    /**
     * The post_status that stores a given LayoutStatus.
     *
     * @param  string $status One of LayoutStatus::all().
     * @return string|null    Null for an unknown status.
     */
    public static function for_status( string $status ): ?string {
        $map = [
            LayoutStatus::DRAFT     => self::DRAFT,
            LayoutStatus::PENDING   => self::PENDING,
            LayoutStatus::SCHEDULED => self::SCHEDULED,
            LayoutStatus::PUBLISHED => self::PUBLISHED,
            LayoutStatus::ARCHIVED  => self::ARCHIVED,
        ];

        return $map[ $status ] ?? null;
    }
}
