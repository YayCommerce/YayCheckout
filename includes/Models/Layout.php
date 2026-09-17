<?php
namespace YayCheckout\Models;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Helpers\DateHelper;

defined( 'ABSPATH' ) || exit;

/**
 * A checkout layout. Pure entity: no WordPress calls.
 *
 * Two copies of the markup live on one post: post_content is the working
 * draft, _yaycheckout_published the snapshot customers see. Only publishing
 * writes the snapshot; a customer request never reads the draft. A 'publish'
 * post_status always has one: LayoutPublishSync writes it on the transition
 * and LayoutPublishGuard refuses REST publishes it could not write.
 */
class Layout {

    public const POST_TYPE = 'yay_checkout_layout';

    // Keys are constants so a typo is a fatal, not a silent read of the wrong copy.
    public const META_PUBLISHED          = '_yaycheckout_published';
    public const META_DERIVED            = '_yaycheckout_derived';
    public const META_PUBLISHED_AT       = '_yaycheckout_published_at';
    public const META_PUBLISHED_REVISION = '_yaycheckout_published_revision';
    public const META_RULES              = '_yaycheckout_rules';
    public const META_SELF_TEST          = '_yaycheckout_self_test';

    public const OPTION_ACTIVE = 'yaycheckout_active_layout';

    public int $id       = 0;
    public string $title = '';

    /** The post_content. Never rendered to customers. */
    public string $draft = '';

    /** What the renderer reads. */
    public string $snapshot = '';

    /** GMT MySQL datetime; null until first published. */
    public ?string $published_at = null;
    /** GMT post_date while post_status is 'future'; null otherwise. */
    public ?string $scheduled_at    = null;
    public ?int $published_revision = null;
    public bool $is_active          = false;

    /** LayoutStatus value; the wire status as-is. */
    public string $post_status = LayoutStatus::DRAFT;

    /** Pro assignment rules. Unused in 1.x. */
    public array $rules = [];

    /** Regenerated from the snapshot at publish time. */
    public array $derived = [];

    /**
     * @param array $meta Bulk meta as returned by get_post_meta( $id ), unserialized.
     */
    public static function from_post( \WP_Post $post, array $meta, bool $is_active ): self {
        $layout = new self();

        $layout->id           = (int) $post->ID;
        $layout->title        = (string) $post->post_title;
        $layout->draft        = (string) $post->post_content;
        $layout->snapshot     = (string) self::meta_value( $meta, self::META_PUBLISHED, '' );
        $layout->post_status  = (string) $post->post_status;
        $layout->scheduled_at = LayoutStatus::SCHEDULED === $layout->post_status ? (string) $post->post_date_gmt : null;

        $published_at = self::meta_value( $meta, self::META_PUBLISHED_AT, null );
        $revision     = self::meta_value( $meta, self::META_PUBLISHED_REVISION, null );

        $layout->published_at       = ( null === $published_at || '' === $published_at ) ? null : (string) $published_at;
        $layout->published_revision = ( null === $revision || '' === $revision ) ? null : (int) $revision;
        $layout->is_active          = $is_active;
        $layout->rules              = (array) self::meta_value( $meta, self::META_RULES, [] );
        $layout->derived            = (array) self::meta_value( $meta, self::META_DERIVED, [] );

        return $layout;
    }

    public function has_unpublished_changes(): bool {
        return $this->is_published() && $this->draft !== $this->snapshot;
    }

    /** List row: no markup, so a large store does not ship every draft. Timestamps as ISO 8601. */
    public function to_summary_array(): array {
        return [
            'id'                      => $this->id,
            'title'                   => $this->title,
            'status'                  => $this->post_status,
            'has_unpublished_changes' => $this->has_unpublished_changes(),
            'is_active'               => $this->is_active,
            'published_at'            => DateHelper::to_iso8601( $this->published_at ),
            'scheduled_at'            => DateHelper::to_iso8601( $this->scheduled_at ),
        ];
    }

    /** Serialized shape for REST. Mirrored exactly by the SPA's strict wire schema. */
    public function to_array(): array {
        return array_merge(
            $this->to_summary_array(),
            [
                'draft'              => $this->draft,
                'snapshot'           => $this->snapshot,
                'published_revision' => $this->published_revision,
                'derived'            => $this->derived,
            ]
        );
    }

    public function is_published(): bool {
        return LayoutStatus::PUBLISHED === $this->post_status;
    }

    /**
     * Unwraps the array-of-arrays get_post_meta( $id ) returns.
     *
     * @return mixed
     */
    private static function meta_value( array $meta, string $key, $fallback ) {
        if ( ! isset( $meta[ $key ] ) ) {
            return $fallback;
        }

        $value = $meta[ $key ];

        if ( is_array( $value ) && array_key_exists( 0, $value ) ) {
            return $value[0];
        }

        return $value;
    }
}
