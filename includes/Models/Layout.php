<?php
namespace YayCheckout\Models;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Helpers\DateHelper;

defined( 'ABSPATH' ) || exit;

/**
 * A checkout layout. Pure entity: no WordPress calls.
 *
 * One copy of the markup, post_content; 'publish' in post_status is what
 * makes it live. Publishing is a status change, nothing else.
 */
class Layout {

    public const POST_TYPE = 'yay_checkout_layout';

    // Keys are constants so a typo is a fatal, not a silent read of nothing.
    public const META_RULES     = '_yaycheckout_rules';
    public const META_SELF_TEST = '_yaycheckout_self_test';

    public const OPTION_ACTIVE = 'yaycheckout_active_layout';

    public int $id       = 0;
    public string $title = '';

    /** The post_content: block markup. */
    public string $content = '';

    /** GMT post_date while post_status is 'publish'; null otherwise. */
    public ?string $published_at = null;
    /** GMT post_date while post_status is 'future'; null otherwise. */
    public ?string $scheduled_at = null;
    public bool $is_active       = false;

    /** LayoutStatus value; the wire status as-is. */
    public string $post_status = LayoutStatus::DRAFT;

    /** Pro assignment rules. Unused in 1.x. */
    public array $rules = [];

    /**
     * @param array $meta Bulk meta as returned by get_post_meta( $id ), unserialized.
     */
    public static function from_post( \WP_Post $post, array $meta, bool $is_active ): self {
        $layout = new self();

        $layout->id           = (int) $post->ID;
        $layout->title        = (string) $post->post_title;
        $layout->content      = (string) $post->post_content;
        $layout->post_status  = (string) $post->post_status;
        $layout->published_at = LayoutStatus::PUBLISHED === $layout->post_status ? (string) $post->post_date_gmt : null;
        $layout->scheduled_at = LayoutStatus::SCHEDULED === $layout->post_status ? (string) $post->post_date_gmt : null;
        $layout->is_active    = $is_active;
        $layout->rules        = (array) self::meta_value( $meta, self::META_RULES, [] );

        return $layout;
    }

    /** List row: no markup, so a large store does not ship every draft. Timestamps as ISO 8601. */
    public function to_summary_array(): array {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'status'       => $this->post_status,
            'is_active'    => $this->is_active,
            'published_at' => DateHelper::to_iso8601( $this->published_at ),
            'scheduled_at' => DateHelper::to_iso8601( $this->scheduled_at ),
        ];
    }

    /** Serialized shape for REST. Mirrored exactly by the SPA's strict wire schema. */
    public function to_array(): array {
        return array_merge(
            $this->to_summary_array(),
            [ 'content' => $this->content ]
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
