<?php
namespace YayCheckout\Models;

use YayCheckout\Enums\LayoutPostStatus;
use YayCheckout\Enums\LayoutStatus;

defined( 'ABSPATH' ) || exit;

/**
 * A checkout layout.
 *
 * Dumb entity: it holds data and the storage-key constants, and makes no
 * WordPress calls. Everything it needs is handed to from_post() already
 * fetched, which keeps the draft/snapshot rules readable in one file and
 * lets this class be reasoned about without WordPress loaded.
 *
 * A layout carries two copies of its markup at once:
 *
 *   post_content            the working draft — what the editor saves
 *   _yaycheckout_published  the snapshot — what customers actually see
 *
 * Publishing copies draft to snapshot. Nothing else ever writes the snapshot,
 * and a customer request never reads the draft.
 */
class Layout {

    public const POST_TYPE = 'yay_checkout_layout';

    /*
     * Storage keys live here and nowhere else. With two copies of the markup on
     * one post, a mistyped key silently reads or writes the wrong one — the
     * renderer serves a draft, or a publish writes nothing — with no error and
     * no failing test. A constant turns that into a fatal.
     */
    public const META_PUBLISHED          = '_yaycheckout_published';
    public const META_DERIVED            = '_yaycheckout_derived';
    public const META_PUBLISHED_AT       = '_yaycheckout_published_at';
    public const META_PUBLISHED_REVISION = '_yaycheckout_published_revision';
    public const META_RULES              = '_yaycheckout_rules';
    public const META_SELF_TEST          = '_yaycheckout_self_test';

    public const OPTION_ACTIVE = 'yaycheckout_active_layout';

    public int $id       = 0;
    public string $title = '';

    /** Working draft markup (post_content). Never rendered to customers. */
    public string $draft = '';

    /** Published snapshot markup. What the renderer reads. */
    public string $snapshot = '';

    public ?string $published_at    = null;
    /** GMT post_date while post_status is 'future'; null otherwise. */
    public ?string $scheduled_at    = null;
    public ?int $published_revision = null;
    public bool $is_active          = false;

    /** WordPress post_status (LayoutPostStatus). Never serialized to REST. */
    public string $post_status = LayoutPostStatus::DRAFT;

    /** Pro assignment rules. Unused in 1.x. */
    public array $rules = [];

    /** Caches regenerated from the snapshot at publish time. */
    public array $derived = [];

    /**
     * @param  \WP_Post $post
     * @param  array    $meta      Raw bulk meta as returned by get_post_meta( $id ).
     * @param  bool     $is_active
     * @return self
     */
    public static function from_post( \WP_Post $post, array $meta, bool $is_active ): self {
        $layout = new self();

        $layout->id           = (int) $post->ID;
        $layout->title        = (string) $post->post_title;
        $layout->draft        = (string) $post->post_content;
        $layout->snapshot     = (string) self::meta_value( $meta, self::META_PUBLISHED, '' );
        $layout->post_status  = (string) $post->post_status;
        $layout->scheduled_at = LayoutPostStatus::SCHEDULED === $layout->post_status ? (string) $post->post_date_gmt : null;

        $published_at = self::meta_value( $meta, self::META_PUBLISHED_AT, null );
        $revision     = self::meta_value( $meta, self::META_PUBLISHED_REVISION, null );

        $layout->published_at       = ( null === $published_at || '' === $published_at ) ? null : (string) $published_at;
        $layout->published_revision = ( null === $revision || '' === $revision ) ? null : (int) $revision;
        $layout->is_active          = $is_active;
        $layout->rules              = (array) self::meta_value( $meta, self::META_RULES, [] );
        $layout->derived            = (array) self::meta_value( $meta, self::META_DERIVED, [] );

        return $layout;
    }

    /**
     * Product status for REST (LayoutStatus). Archived wins so a published
     * layout that has been archived keeps its snapshot (restore must return
     * Published, not Draft) without appearing as live in the listed tab.
     * Scheduled and Pending likewise come from post_status alone: they
     * describe what will happen to the draft, regardless of whether an
     * earlier snapshot is already live.
     */
    public function status(): string {
        switch ( $this->post_status ) {
            case LayoutPostStatus::ARCHIVED:
                return LayoutStatus::ARCHIVED;
            case LayoutPostStatus::SCHEDULED:
                return LayoutStatus::SCHEDULED;
            case LayoutPostStatus::PENDING:
                return LayoutStatus::PENDING;
            case LayoutPostStatus::PUBLISHED:
                return LayoutStatus::PUBLISHED;
        }

        return $this->is_published() ? LayoutStatus::PUBLISHED : LayoutStatus::DRAFT;
    }

    public function has_unpublished_changes(): bool {
        return $this->is_published() && $this->draft !== $this->snapshot;
    }

    /**
     * List row. Markup stays off the collection so a Pro store with dozens of
     * layouts does not ship every draft over the wire.
     *
     * @return array
     */
    public function to_summary_array(): array {
        return [
            'id'                      => $this->id,
            'title'                   => $this->title,
            'status'                  => $this->status(),
            'has_unpublished_changes' => $this->has_unpublished_changes(),
            'is_active'               => $this->is_active,
            'published_at'            => $this->published_at,
            'scheduled_at'            => $this->scheduled_at,
        ];
    }

    /**
     * Serialized shape for REST. Keys are snake_case like every payload field
     * (see docs/code-standards.md, REST); the wire zod schema in apps/admin
     * mirrors this exactly and lib/api/layout.api.ts converts it to the
     * camelCase `Layout` type the rest of the SPA uses.
     *
     * @return array
     */
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
        return null !== $this->published_at && '' !== $this->snapshot;
    }

    /**
     * Unwrap one value from the array-of-arrays that get_post_meta( $id )
     * returns. Without this every field silently becomes a one-element array.
     *
     * @param  array  $meta
     * @param  string $key
     * @param  mixed  $fallback
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
