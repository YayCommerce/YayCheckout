<?php
namespace YayCheckout\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * The post_status values a layout can hold. Storage detail; never serialized
 * to REST (LayoutStatus is). Constants, not a native enum: PHP 7.4 support.
 */
final class LayoutPostStatus {

    public const DRAFT     = 'draft';
    public const PENDING   = 'pending';
    public const SCHEDULED = 'future';

    /**
     * Means the draft was pushed, not that shoppers see it; Layout::status()
     * still needs a snapshot.
     *
     * TODO: guard anonymous wp/v2 reads — core serves any 'publish' post
     * without consulting the capability map. Add a manage_woocommerce check on
     * rest_pre_dispatch; 'private' fights the editor's Publish button and cron.
     */
    public const PUBLISHED = 'publish';

    /** WP cron empties trash after EMPTY_TRASH_DAYS, so archive is not indefinite. */
    public const ARCHIVED = 'trash';

    /**
     * @return string|null Null for an unknown status.
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
