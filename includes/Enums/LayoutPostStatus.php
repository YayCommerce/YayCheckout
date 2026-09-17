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
     * Core's 'publish'. Says the draft was pushed, not that shoppers see it:
     * serving is the active-layout option, and Layout::status() still needs
     * a snapshot to report Published.
     *
     * TODO: guard anonymous wp/v2 reads. Core's REST read check passes any
     * 'publish' post without consulting the capability map, so wp/v2 serves
     * published layouts to logged-out requests. Accepted for now; add a
     * manage_woocommerce check on rest_pre_dispatch or the
     * rest_yay_checkout_layout_query filter. 'private' was rejected because it
     * fights the editor's Publish button and cron.
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
