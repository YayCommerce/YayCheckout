<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Answers questions about a layout's block markup: its sections, its fields, and whether it may
 * be published. Pure: every method walks one flat, document-ordered block list, so nesting never
 * matters. Reads never throw; publish_error() is where a layout is refused.
 */
class LayoutMarkupReader {

    private const CHECKOUT          = 'yaycheckout/checkout';
    private const EMAIL             = 'yaycheckout/email';
    public const FIELD              = 'yaycheckout/field';
    private const EXTRA_FIELD       = 'yaycheckout/extra-field';
    private const ORDER_NOTE        = 'yaycheckout/order-note';
    private const SLOT              = 'yaycheckout/slot';
    public const BILLING            = 'yaycheckout/billing';
    public const SHIPPING           = 'yaycheckout/shipping';
    private const DIFFERENT_ADDRESS = 'yaycheckout/different-address';
    private const STEPS             = 'yaycheckout/steps';
    private const STEP              = 'yaycheckout/step';

    /**
     * A Key is a posted field name and an order meta key. PHP rewrites spaces
     * and dots in posted names, so those values never arrive; a leading
     * underscore reaches WooCommerce's internal order keys, where
     * WC_Data::update_meta_data() calls the matching setter instead.
     */
    public const EXTRA_FIELD_KEY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_]*$/';

    /** WooCommerce's posted name for the order note, which the Order note block prints. */
    public const ORDER_NOTE_KEY = 'order_comments';

    /** Section block => slot name it occupies. */
    private const SECTION_SLOTS = [
        self::EMAIL                    => 'email',
        self::BILLING                  => 'billing',
        self::SHIPPING                 => 'shipping',
        'yaycheckout/shipping-methods' => 'shipping_methods',
        'yaycheckout/express-pay'      => 'express_pay',
        'yaycheckout/payment'          => 'payment',
        'yaycheckout/order-summary'    => 'order_summary',
        'yaycheckout/place-order'      => 'place_order',
    ];

    /**
     * Section slots plus each named slot block, in document order, unique.
     *
     * @return string[]
     */
    public static function slots( string $markup ): array {
        $slots = [];

        foreach ( self::blocks( $markup ) as $block ) {
            if ( isset( self::SECTION_SLOTS[ $block['blockName'] ] ) ) {
                $slots[] = self::SECTION_SLOTS[ $block['blockName'] ];
            } elseif ( self::SLOT === $block['blockName'] ) {
                $slots[] = sanitize_key( self::text( $block['attrs'], 'slotName' ) );
            }
        }

        return array_values( array_unique( array_filter( $slots, 'strlen' ) ) );
    }

    /**
     * WooCommerce address fields by Key: the Email address block is billing_email, each Field
     * block with a Key is its own. A later block with the same Key wins.
     *
     * @return array<string, array>
     */
    public static function fields( string $markup ): array {
        $fields = [];

        foreach ( self::blocks( $markup ) as $block ) {
            $attrs = $block['attrs'];

            if ( self::EMAIL === $block['blockName'] ) {
                $attrs = [
                    'fieldKey'     => 'billing_email',
                    'type'         => 'email',
                    'label'        => self::plain_text( $attrs, 'label' ),
                    'placeholder'  => self::text( $attrs, 'placeholder' ),
                    'required'     => true, // RequiredFieldOverlay::is_required() owns the email-always-required rule.
                    'requiredMark' => self::text( $attrs, 'requiredMark' ),
                    'optionalText' => self::text( $attrs, 'optionalText' ),
                ];
            } elseif ( self::FIELD !== $block['blockName'] ) {
                continue;
            }

            $key = trim( self::text( $attrs, 'fieldKey' ) );

            if ( '' === $key ) {
                continue;
            }

            $fields[ $key ] = self::field_entry( $key, self::text( $attrs, 'type', 'text' ), $attrs ) + [
                'description'  => self::text( $attrs, 'description' ),
                'enabled'      => true,
                'autocomplete' => self::text( $attrs, 'autocomplete' ),
                'validate'     => isset( $attrs['validate'] ) && is_array( $attrs['validate'] ) ? array_values( array_map( 'strval', $attrs['validate'] ) ) : [],
                'width'        => self::text( $attrs, 'width', 'full' ),
            ];
        }

        return $fields;
    }

    /**
     * Merchant extra fields by Key. A block without a valid Key renders
     * nothing, so it is skipped.
     *
     * @return array<string, array>
     */
    public static function extra_fields( string $markup ): array {
        $fields = [];

        foreach ( self::blocks( $markup ) as $block ) {
            $key = self::EXTRA_FIELD === $block['blockName'] ? self::extra_field_key( $block['attrs'] ) : '';

            if ( '' === $key ) {
                continue;
            }

            $attrs          = $block['attrs'];
            $fields[ $key ] = self::field_entry( $key, self::text( $attrs, 'type', 'text' ), $attrs ) + [
                'options'  => self::options( $attrs['options'] ?? [] ),
                'remember' => ! empty( $attrs['rememberValue'] ),
            ];
        }

        return $fields;
    }

    /**
     * The Order note block as a field entry for WooCommerce's order_comments, with the keys of
     * fields()' entries that a note has; null when the layout has none. The block allows one, so the first wins.
     *
     * @return array<string, mixed>|null
     */
    public static function order_note( string $markup ): ?array {
        $block = self::first_block( $markup, self::ORDER_NOTE );

        if ( null === $block ) {
            return null;
        }

        return self::field_entry( self::ORDER_NOTE_KEY, 'textarea', $block['attrs'] ) + [
            'description' => self::text( $block['attrs'], 'description' ),
        ];
    }

    /**
     * The first block named $name, at any depth, in document order; null when the layout has none.
     *
     * @return array|null A parsed block, attrs always an array.
     */
    public static function first_block( string $markup, string $name ): ?array {
        foreach ( self::blocks( $markup ) as $block ) {
            if ( $name === $block['blockName'] ) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Saved titles of the first Steps block's Step children, in order; '' for a step left untitled.
     * Empty without a Steps block. A layout holds one Checkout, so that is also the first Steps block inside it.
     *
     * @return string[]
     */
    public static function step_titles( string $markup ): array {
        $steps  = self::first_block( $markup, self::STEPS );
        $titles = [];

        foreach ( null !== $steps ? $steps['innerBlocks'] : [] as $child ) {
            if ( self::STEP === ( $child['blockName'] ?? '' ) ) {
                $titles[] = self::plain_text( is_array( $child['attrs'] ?? null ) ? $child['attrs'] : [], 'title' );
            }
        }

        return $titles;
    }

    /** Whether the layout has a Checkout block, without parsing it. */
    public static function has_checkout( string $markup ): bool {
        return has_block( self::CHECKOUT, $markup );
    }

    /** Whether the layout has an Order note block, without parsing it. */
    public static function has_order_note( string $markup ): bool {
        return has_block( self::ORDER_NOTE, $markup );
    }

    /** Whether the layout has a Billing section, without parsing it. */
    public static function has_billing( string $markup ): bool {
        return has_block( self::BILLING, $markup );
    }

    /** Whether the layout has a Shipping section, without parsing it. */
    public static function has_shipping( string $markup ): bool {
        return has_block( self::SHIPPING, $markup );
    }

    /** Whether the layout has a Different address block, without parsing it. */
    public static function has_different_address( string $markup ): bool {
        return has_block( self::DIFFERENT_ADDRESS, $markup );
    }

    /**
     * The entry keys every field reader shares: what the shopper sees and whether it is required.
     *
     * @param array $attrs Raw block attributes.
     */
    private static function field_entry( string $key, string $type, array $attrs ): array {
        return [
            'fieldKey'     => $key,
            'type'         => $type,
            'label'        => self::plain_text( $attrs, 'label' ),
            'placeholder'  => self::text( $attrs, 'placeholder' ),
            'required'     => ! empty( $attrs['required'] ),
            'requiredMark' => trim( self::text( $attrs, 'requiredMark' ) ),
            'optionalText' => trim( self::text( $attrs, 'optionalText' ) ),
        ];
    }

    /**
     * Always empty: no block declares an order mode. Kept so the layout's shape is complete.
     *
     * @return string[]
     */
    public static function order_modes( string $markup ): array {
        unset( $markup );
        return [];
    }

    /**
     * Why the layout cannot be activated, or saved while live, or '' when it can. JavaScript copies of these rules live in
     * packages/blocks/src/checkout/publish-guard.ts, packages/blocks/src/shared/extra-field-keys.ts
     * and packages/blocks/src/extra-field/edit.tsx; change them together.
     */
    public static function publish_error( string $markup ): string {
        if ( ! self::has_checkout( $markup ) ) {
            return __( 'Add a Checkout block to this layout before activating it.', 'yaycheckout' );
        }

        $keys = [];

        foreach ( self::blocks( $markup ) as $block ) {
            if ( self::EXTRA_FIELD === $block['blockName'] ) {
                $keys[] = trim( self::text( $block['attrs'], 'fieldKey' ) );
            }
        }

        if ( in_array( '', $keys, true ) ) {
            return __( 'Every extra field needs a Key before this layout can be activated.', 'yaycheckout' );
        }

        $malformed = array_values( array_unique( preg_grep( self::EXTRA_FIELD_KEY_PATTERN, $keys, PREG_GREP_INVERT ) ) );

        if ( ! empty( $malformed ) ) {
            return sprintf(
                /* translators: %s: comma-separated extra field keys. */
                __( 'Extra field Keys may use only letters, numbers and underscores, and must not start with an underscore: %s.', 'yaycheckout' ),
                implode( ', ', $malformed )
            );
        }

        $duplicates = array_unique( array_diff_assoc( $keys, array_unique( $keys ) ) );

        if ( ! empty( $duplicates ) ) {
            return sprintf(
                /* translators: %s: comma-separated extra field keys. */
                __( 'Extra field Keys must be unique. Used more than once: %s.', 'yaycheckout' ),
                implode( ', ', $duplicates )
            );
        }

        return '';
    }

    /**
     * An extra field's Key: its posted name and order meta key, trimmed; ''
     * when missing or not a valid Key, so the field renders and stores nothing.
     *
     * @param mixed $attrs Block attributes.
     */
    public static function extra_field_key( $attrs ): string {
        $key = is_array( $attrs ) ? trim( self::text( $attrs, 'fieldKey' ) ) : '';

        return 1 === preg_match( self::EXTRA_FIELD_KEY_PATTERN, $key ) ? $key : '';
    }

    /**
     * Every block in the markup, parents before children, in document order.
     * Loose HTML between blocks (null blockName) is dropped.
     */
    private static function blocks( string $markup ): array {
        return self::flatten( parse_blocks( $markup ) );
    }

    /** Blocks with their descendants, parents first, in document order; attrs always an array. */
    public static function flatten( array $blocks ): array {
        $flat = [];

        foreach ( $blocks as $block ) {
            if ( empty( $block['blockName'] ) ) {
                continue;
            }

            // Malformed attribute JSON parses to null; reads must still not throw.
            $block['attrs'] = is_array( $block['attrs'] ) ? $block['attrs'] : [];
            $flat[]         = $block;
            $flat           = array_merge( $flat, self::flatten( $block['innerBlocks'] ) );
        }

        return $flat;
    }

    /** A string attribute, or $fallback when missing, not a string, or empty. */
    public static function text( array $attrs, string $name, string $fallback = '' ): string {
        return isset( $attrs[ $name ] ) && is_string( $attrs[ $name ] ) && '' !== $attrs[ $name ] ? $attrs[ $name ] : $fallback;
    }

    /**
     * A string attribute edited on the canvas, as plain text. The editor saves rich text with
     * HTML entities (& as &amp;), so print it through esc_html() only after this decodes them.
     */
    public static function plain_text( array $attrs, string $name, string $fallback = '' ): string {
        $text = trim( html_entity_decode( wp_strip_all_tags( self::text( $attrs, $name ), true ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );

        return '' !== $text ? $text : $fallback;
    }

    /**
     * An extra field's choices as value => label. A blank value becomes its label's slug, a blank
     * label shows the value, and a row left without a value is dropped. Values are cleaned the way
     * a posted value is, so every rendered choice posts back as a valid selection.
     *
     * @param  mixed $options The block's options attribute.
     * @return array<string, string>
     */
    public static function options( $options ): array {
        $map = [];

        foreach ( is_array( $options ) ? $options : [] as $option ) {
            if ( ! is_array( $option ) ) {
                continue;
            }

            $value = isset( $option['value'] ) ? (string) $option['value'] : '';
            $label = isset( $option['label'] ) ? (string) $option['label'] : '';

            $value = sanitize_text_field( '' !== $value ? $value : sanitize_title( $label ) );

            if ( '' === $value ) {
                continue;
            }

            $map[ $value ] = '' === $label ? $value : $label;
        }

        return $map;
    }
}
