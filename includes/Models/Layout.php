<?php
namespace YayCheckout\Models;

use YayCheckout\Enums\ConditionType;
use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Helpers\DateHelper;

defined( 'ABSPATH' ) || exit;

/**
 * A checkout layout: one post whose post_content is the markup and whose 'publish'
 * status makes it live. Pure entity: no WordPress calls.
 */
class Layout {

    public const POST_TYPE = 'yay_checkout_layout';

    public const META_RULES      = '_yaycheckout_rules';
    public const META_SELF_TEST  = '_yaycheckout_self_test';
    public const META_DESIGN     = '_yaycheckout_design';
    public const META_TEMPLATE   = '_yaycheckout_template';
    public const META_LOGGED_OUT = '_yaycheckout_logged_out_content';
    public const META_THANK_YOU  = '_yaycheckout_thank_you_content';

    public const OPTION_ACTIVE = 'yaycheckout_active_layout';

    /** Export envelope identity; `plugin_version` carries compatibility, so there is no separate format version. */
    public const EXPORT_FORMAT = 'yaycheckout-layout';

    public int $id       = 0;
    public string $title = '';

    /** The post_content: block markup. */
    public string $content = '';

    /** What a guest who must log in sees instead of $content; '' when the merchant has not saved one. */
    public string $logged_out_content = '';

    /** The order-received page shoppers see while this layout is active; '' when the layout has none. */
    public string $thank_you_content = '';

    /** GMT post_date while post_status is 'publish'; null otherwise. */
    public ?string $published_at = null;
    /** GMT post_date while post_status is 'future'; null otherwise. */
    public ?string $scheduled_at = null;
    /** GMT post_modified; LayoutRepository fills it in when core left it zero. */
    public ?string $updated_at = null;
    public bool $is_active     = false;

    /** LayoutStatus value; the wire status as-is. */
    public string $post_status = LayoutStatus::DRAFT;

    /**
     * Pro assignment rules: groups ORed, conditions inside a group ANDed. Each condition is
     * { type, operator, value } per ConditionType; [] means none.
     *
     * @var array<int, array<int, array{type: string, operator: string, value: mixed}>>
     */
    public array $rules = [];

    /** This layout's design; every token empty when it has none. */
    public LayoutDesign $design;

    /** The LayoutTemplateCatalogue slug it was created from, which Reset restores; '' when unknown (older or imported). */
    public string $template = '';

    /** Whether the layout has a thank-you page to serve on order-received. */
    public function has_thank_you(): bool {
        return '' !== trim( $this->thank_you_content );
    }

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
        $layout->updated_at   = (string) $post->post_modified_gmt;
        $layout->is_active    = $is_active;
        $layout->rules        = (array) self::meta_value( $meta, self::META_RULES, [] );
        $layout->design       = LayoutDesign::from_meta( self::meta_value( $meta, self::META_DESIGN, null ) );
        $layout->template     = (string) self::meta_value( $meta, self::META_TEMPLATE, '' );

        $layout->logged_out_content = (string) self::meta_value( $meta, self::META_LOGGED_OUT, '' );
        $layout->thank_you_content  = (string) self::meta_value( $meta, self::META_THANK_YOU, '' );

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
            'updated_at'   => DateHelper::to_iso8601( $this->updated_at ),
            'rules'        => $this->rules,
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
     * Validates and normalizes assignment rules: list values de-duplicated.
     * Strict, not forgiving: an incomplete condition is refused, never stored half-filled.
     *
     * @param mixed $input Decoded request body `rules`.
     * @return array Clean rules, same shape as $rules.
     * @throws \InvalidArgumentException Naming the first invalid group or condition.
     */
    public static function sanitize_rules( $input ): array {
        if ( ! is_array( $input ) || array_values( $input ) !== $input ) {
            throw new \InvalidArgumentException( 'Rules must be a list of groups.' );
        }

        $clean = [];

        foreach ( $input as $group_index => $group ) {
            if ( ! is_array( $group ) || [] === $group || array_values( $group ) !== $group ) {
                throw new \InvalidArgumentException( esc_html( sprintf( 'Group %d must be a non-empty list of conditions.', $group_index + 1 ) ) );
            }

            foreach ( $group as $condition_index => $condition ) {
                $clean[ $group_index ][ $condition_index ] = self::sanitize_condition( $condition, sprintf( 'group %d, condition %d', $group_index + 1, $condition_index + 1 ) );
            }
        }

        return $clean;
    }

    /**
     * @param string $where Position for the error message.
     * @return array{type: string, operator: string, value: mixed}
     * @throws \InvalidArgumentException When the type, operator or value is invalid.
     */
    private static function sanitize_condition( $condition, string $where ): array {
        $definitions = ConditionType::definitions();
        $type        = $condition['type'] ?? null;

        if ( ! is_string( $type ) || ! isset( $definitions[ $type ] ) ) {
            throw new \InvalidArgumentException( esc_html( "Unknown condition type in $where." ) );
        }

        if ( ! in_array( $condition['operator'] ?? null, $definitions[ $type ]['operators'], true ) ) {
            throw new \InvalidArgumentException( esc_html( "Invalid operator in $where." ) );
        }

        $value = self::sanitize_rule_value( $condition['value'] ?? null, $definitions[ $type ] );

        if ( null === $value ) {
            throw new \InvalidArgumentException( esc_html( "Invalid value in $where." ) );
        }

        return [
            'type'     => $type,
            'operator' => $condition['operator'],
            'value'    => $value,
        ];
    }

    /**
     * @param array $definition One entry of ConditionType::definitions().
     * @return mixed Normalized value, or null when invalid.
     */
    private static function sanitize_rule_value( $value, array $definition ) {
        switch ( $definition['value'] ) {
            case ConditionType::VALUE_KEYS:
                $list = is_array( $value ) ? $value : [];
                // Role slugs: plain identifiers, so a strict pattern replaces escaping.
                $invalid = array_filter( $list, static fn( $key ) => ! is_string( $key ) || ! preg_match( '/^[A-Za-z0-9_.-]{1,100}$/', $key ) );
                // Unique only once all are strings: array_unique then compares as strings, so '10' and '1e1' stay two keys.
                return [] === $list || [] !== $invalid ? null : array_values( array_unique( $list ) );

            case ConditionType::VALUE_CHOICE:
                return in_array( $value, $definition['choices'], true ) ? $value : null;
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
