<?php
namespace YayCheckout\Models;

use YayCheckout\Enums\ConditionType;
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

    /** Export envelope identity; `plugin_version` carries compatibility, so there is no separate format version. */
    public const EXPORT_FORMAT = 'yaycheckout-layout';

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

    /**
     * Pro assignment rules: groups ORed, conditions inside a group ANDed. Each condition is
     * { type, operator, value } per ConditionType; [] means no rule, so the layout is never assigned.
     *
     * @var array<int, array<int, array{type: string, operator: string, value: mixed}>>
     */
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

    /** How many of our blocks the markup holds. Each opens with one `<!-- wp:yaycheckout/`; closers read `<!-- /wp:`. */
    public static function block_count( string $markup ): int {
        return substr_count( $markup, '<!-- wp:yaycheckout/' );
    }

    /** List row: no markup, so a large store does not ship every draft. Timestamps as ISO 8601. */
    public function to_summary_array(): array {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'status'       => $this->post_status,
            'is_active'    => $this->is_active,
            'blocks'       => self::block_count( $this->content ),
            'size'         => strlen( $this->content ),
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

    /**
     * Validates and normalizes assignment rules: ids to int, amounts to float, list values de-duplicated.
     * Strict, not forgiving: the SPA only sends complete conditions, so anything else is a bug to surface.
     *
     * @param mixed $input Decoded request body `rules`.
     * @return array Clean rules, same shape as $rules.
     * @throws \InvalidArgumentException Naming the first invalid group or condition.
     */
    public static function sanitize_rules( $input ): array {
        if ( ! is_array( $input ) || array_values( $input ) !== $input ) {
            throw new \InvalidArgumentException( 'Rules must be a list of groups.' );
        }

        $definitions = ConditionType::definitions();
        $clean       = [];

        foreach ( $input as $g => $group ) {
            if ( ! is_array( $group ) || [] === $group || array_values( $group ) !== $group ) {
                throw new \InvalidArgumentException( sprintf( 'Group %d must be a non-empty list of conditions.', $g + 1 ) );
            }

            foreach ( $group as $c => $condition ) {
                $where = sprintf( 'group %d, condition %d', $g + 1, $c + 1 );
                $type  = $condition['type'] ?? null;

                if ( ! is_string( $type ) || ! isset( $definitions[ $type ] ) ) {
                    throw new \InvalidArgumentException( "Unknown condition type in $where." );
                }

                if ( ! in_array( $condition['operator'] ?? null, $definitions[ $type ]['operators'], true ) ) {
                    throw new \InvalidArgumentException( "Invalid operator in $where." );
                }

                $value = self::sanitize_rule_value( $condition['value'] ?? null, $definitions[ $type ]['value'] );

                if ( null === $value ) {
                    throw new \InvalidArgumentException( "Invalid value in $where." );
                }

                $clean[ $g ][ $c ] = [
                    'type'     => $type,
                    'operator' => $condition['operator'],
                    'value'    => $value,
                ];
            }
        }

        return $clean;
    }

    /**
     * @param mixed  $value
     * @param string $kind ConditionType::VALUE_*.
     * @return mixed Normalized value, or null when invalid.
     */
    private static function sanitize_rule_value( $value, string $kind ) {
        switch ( $kind ) {
            case ConditionType::VALUE_KEYS:
                // Country codes, role slugs, gateway ids: plain identifiers, so a strict pattern replaces escaping.
                $keys = is_array( $value ) ? array_values( array_unique( $value, SORT_REGULAR ) ) : [];
                foreach ( $keys as $key ) {
                    if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z0-9_.-]{1,100}$/', $key ) ) {
                        return null;
                    }
                }
                return [] === $keys ? null : $keys;

            case ConditionType::VALUE_IDS:
                // The SPA holds ids as strings (select values); accept digit strings, store ints.
                $ids = [];
                foreach ( is_array( $value ) ? $value : [] as $id ) {
                    if ( ! ( is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ) || (int) $id <= 0 ) {
                        return null;
                    }
                    $ids[] = (int) $id;
                }
                $ids = array_values( array_unique( $ids ) );
                return [] === $ids ? null : $ids;

            case ConditionType::VALUE_DEVICE:
                return in_array( $value, ConditionType::DEVICES, true ) ? $value : null;

            case ConditionType::VALUE_AMOUNT:
                return ( is_int( $value ) || is_float( $value ) ) && $value >= 0 ? (float) $value : null;
        }

        return null;
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
