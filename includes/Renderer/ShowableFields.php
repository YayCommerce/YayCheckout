<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * The layout fields whose value can be shown back to the shopper, and that value as the form shows it.
 * Field Value's render.php and LiveValues read them; shared/showable-fields.ts is the editor's copy.
 * No hooks.
 */
final class ShowableFields {

    /**
     * Mirrored by UNSHOWABLE_TYPES in packages/blocks/src/shared/showable-fields.ts: a checkbox's value
     * is always 1, a password must never be echoed, and a hidden field holds nothing typed.
     */
    private const UNSHOWABLE_TYPES = [ 'checkbox', 'password', 'hidden' ];

    /** @var array<string, array{fields: array, extra: array, labels: array<string, string>}> Parsed layouts by md5 of their markup. */
    private static $parsed = [];

    /**
     * Key => label of each field the layout prints that can be shown, in layout order; any other key could
     * read a value it was never meant to show.
     *
     * @return array<string, string>
     */
    public static function labels( string $markup ): array {
        return self::parse( $markup )['labels'];
    }

    /** One field's value as its form control shows it; '' for a key labels() doesn't hold. */
    public static function value( string $key, string $markup ): string {
        $parsed = self::parse( $markup );

        if ( ! isset( $parsed['labels'][ $key ] ) ) {
            return '';
        }

        if ( ! isset( $parsed['fields'][ $key ] ) ) {
            $field = $parsed['extra'][ $key ];

            // As extra-field/render.php: only a remembered field prints a value on load; the script fills the rest.
            if ( empty( $field['remember'] ) ) {
                return '';
            }

            $value = CheckoutFieldDefaults::value( $key );
            $names = [];

            // A choice shows its option's label, as the form does; ticked checkboxes are listed with commas.
            foreach ( is_array( $value ) ? $value : [ $value ] as $choice ) {
                $choice = is_scalar( $choice ) ? trim( (string) $choice ) : '';

                if ( '' !== $choice ) {
                    $names[] = isset( $field['options'][ $choice ] ) ? $field['options'][ $choice ] : $choice;
                }
            }

            return implode( ', ', $names );
        }

        // A field the country's locale hides (State for VN) means nothing, even with a value left over.
        $value = CheckoutFieldDefaults::hidden_for_country( $key ) ? '' : self::read( $key );

        // Country and state show by name, as the selects do. The names carry entities, which esc_html() keeps.
        if ( '' === $value || ! function_exists( 'WC' ) || ! WC()->countries instanceof \WC_Countries ) {
            return $value;
        }

        $names = [];

        if ( '_country' === substr( $key, -8 ) ) {
            $names = WC()->countries->get_countries();
        } elseif ( '_state' === substr( $key, -6 ) ) {
            $country = self::read( substr( $key, 0, -6 ) . '_country' );
            $names   = '' !== $country ? WC()->countries->get_states( $country ) : [];
        }

        return is_array( $names ) && isset( $names[ $value ] ) ? $names[ $value ] : $value;
    }

    private static function read( string $key ): string {
        $value = CheckoutFieldDefaults::value( $key );

        return is_scalar( $value ) ? trim( (string) $value ) : '';
    }

    /** @return array{fields: array, extra: array, labels: array<string, string>} */
    private static function parse( string $markup ): array {
        $hash = md5( $markup );

        if ( isset( self::$parsed[ $hash ] ) ) {
            return self::$parsed[ $hash ];
        }

        $fields = LayoutMarkupReader::fields( $markup );
        $extra  = LayoutMarkupReader::extra_fields( $markup );

        // Keys of the Field blocks inside Billing and inside Shipping, as showable-fields.ts finds them. Email
        // address (billing_email) is found by its block name in fields().
        $in_section = [];

        foreach ( LayoutMarkupReader::flatten( parse_blocks( $markup ) ) as $section ) {
            if ( in_array( $section['blockName'], [ 'yaycheckout/billing', 'yaycheckout/shipping' ], true ) ) {
                foreach ( LayoutMarkupReader::flatten( $section['innerBlocks'] ) as $inner ) {
                    if ( 'yaycheckout/field' === $inner['blockName'] && isset( $inner['attrs']['fieldKey'] ) && is_string( $inner['attrs']['fieldKey'] ) ) {
                        $in_section[ trim( $inner['attrs']['fieldKey'] ) ] = true;
                    }
                }
            }
        }

        // Empty labels fall back as email/render.php, field/render.php and extra-field/render.php do. An Extra
        // field's single checkbox is left out: its value is always 1.
        $labels = [];

        foreach ( $fields as $key => $field ) {
            if ( ( 'billing_email' === $key || isset( $in_section[ $key ] ) ) && ! in_array( $field['type'], self::UNSHOWABLE_TYPES, true ) ) {
                $labels[ $key ] = '' !== $field['label']
                    ? $field['label']
                    : ( 'billing_email' === $key ? __( 'Email address', 'yaycheckout' ) : $key );
            }
        }

        foreach ( $extra as $key => $field ) {
            if ( ! isset( $labels[ $key ] ) && ! ( 'checkbox' === $field['type'] && empty( $field['options'] ) ) ) {
                $labels[ $key ] = '' !== $field['label'] ? $field['label'] : __( 'Extra field', 'yaycheckout' );
            }
        }

        self::$parsed[ $hash ] = [
            'fields' => $fields,
            'extra'  => $extra,
            'labels' => $labels,
        ];

        return self::$parsed[ $hash ];
    }
}
