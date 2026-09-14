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

    /**
     * A layout that has been published at least once.
     *
     * Not 'publish': WP_REST_Posts_Controller::check_read_permission() returns
     * true for any post whose status is 'publish' without consulting the post
     * type's capabilities, so a published layout would be readable through
     * wp/v2 by anonymous visitors. 'private' routes the read through
     * read_post, which this post type maps to manage_woocommerce.
     */
    public const PUBLISHED = 'private';

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
            LayoutStatus::PUBLISHED => self::PUBLISHED,
            LayoutStatus::ARCHIVED  => self::ARCHIVED,
        ];

        return $map[ $status ] ?? null;
    }
}
