<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Answers questions about a layout's block markup: its sections, its fields, and whether it may
 * be published. Pure: every method walks one flat, document-ordered block list, so nesting never
 * matters. Reads never throw; publish_error() is where a layout is refused.
 */
class LayoutMarkupReader {

    private const CHECKOUT    = 'yaycheckout/checkout';
    private const CONTACT     = 'yaycheckout/contact';
    private const FIELD       = 'yaycheckout/field';
    private const EXTRA_FIELD = 'yaycheckout/extra-field';
    private const SLOT        = 'yaycheckout/slot';

    /**
     * A Key is a posted field name and an order meta key. PHP rewrites spaces
     * and dots in posted names, so those values never arrive; a leading
     * underscore reaches WooCommerce's internal order keys, where
     * WC_Data::update_meta_data() calls the matching setter instead.
     */
    public const EXTRA_FIELD_KEY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_]*$/';

    /** Section block => slot name it occupies. */
    private const SECTION_SLOTS = [
        self::CONTACT                  => 'contact',
        'yaycheckout/billing'          => 'billing',
        'yaycheckout/shipping'         => 'shipping',
        'yaycheckout/order-review'     => 'order_review',
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
     * WooCommerce address fields by Key: Contact is billing_email, each Field
     * block with a Key is its own. A later block with the same Key wins.
     *
     * @return array<string, array>
     */
    public static function fields( string $markup ): array {
        $fields = [];

        foreach ( self::blocks( $markup ) as $block ) {
            $attrs = $block['attrs'];

            if ( self::CONTACT === $block['blockName'] ) {
                $attrs = [
                    'fieldKey'     => 'billing_email',
                    'type'         => 'email',
                    'label'        => self::text( $attrs, 'label' ),
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

            $fields[ $key ] = [
                'fieldKey'     => $key,
                'type'         => self::text( $attrs, 'type', 'text' ),
                'label'        => self::plain_text( $attrs, 'label' ),
                'placeholder'  => self::text( $attrs, 'placeholder' ),
                'description'  => self::text( $attrs, 'description' ),
                'required'     => ! empty( $attrs['required'] ),
                'requiredMark' => trim( self::text( $attrs, 'requiredMark' ) ),
                'optionalText' => trim( self::text( $attrs, 'optionalText' ) ),
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
            $fields[ $key ] = [
                'fieldKey'     => $key,
                'type'         => self::text( $attrs, 'type', 'text' ),
                'label'        => self::plain_text( $attrs, 'label' ),
                'placeholder'  => self::text( $attrs, 'placeholder' ),
                'required'     => ! empty( $attrs['required'] ),
                'requiredMark' => trim( self::text( $attrs, 'requiredMark' ) ),
                'optionalText' => trim( self::text( $attrs, 'optionalText' ) ),
                'options'      => self::options( $attrs['options'] ?? [] ),
            ];
        }

        return $fields;
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
     * Why the layout cannot be published, or '' when it can. JavaScript copies of these rules live in
     * packages/blocks/src/checkout/publish-guard.ts, packages/blocks/src/shared/extra-field-keys.ts
     * and packages/blocks/src/extra-field/edit.tsx; change them together.
     */
    public static function publish_error( string $markup ): string {
        if ( ! has_block( self::CHECKOUT, $markup ) ) {
            return __( 'Add a Checkout block to this layout before publishing it.', 'yaycheckout' );
        }

        $keys = [];

        foreach ( self::blocks( $markup ) as $block ) {
            if ( self::EXTRA_FIELD === $block['blockName'] ) {
                $keys[] = trim( self::text( $block['attrs'], 'fieldKey' ) );
            }
        }

        if ( in_array( '', $keys, true ) ) {
            return __( 'Every extra field needs a Key before this layout can be published.', 'yaycheckout' );
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
