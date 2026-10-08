<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * The "+ Add …" link a Field or Extra field can hide behind until the shopper asks for it.
 * Pure: no storage, no hooks. packages/blocks/src/shared/reveal-link.tsx mirrors the rules.
 */
class RevealLink {

    /** Collapsed unless the merchant says otherwise; every other field is shown unless asked. */
    public const APARTMENT_KEYS = [ 'billing_address_2', 'shipping_address_2' ];

    /**
     * Whether the field prints collapsed. A required field never is, so checkout never fails on
     * a field the shopper cannot see, and neither is one already holding the shopper's value, so
     * a reload doesn't hide it. The editor has no value, so reveal-link.tsx has no such rule.
     *
     * @param array $attrs Raw block attributes.
     * @param mixed $value The value the field prints; null, '' and [] are empty.
     */
    public static function collapsed( array $attrs, string $key, bool $required, $value = null ): bool {
        if ( $required || ! in_array( $value, [ null, '', [] ], true ) ) {
            return false;
        }

        if ( isset( $attrs['collapseBehindLink'] ) && is_bool( $attrs['collapseBehindLink'] ) ) {
            return $attrs['collapseBehindLink'];
        }

        return in_array( $key, self::APARTMENT_KEYS, true );
    }

    /**
     * The link's text: the merchant's, or "+ Add" and the label.
     *
     * @param array  $attrs Raw block attributes.
     * @param string $label The field's label as plain text.
     */
    public static function text( array $attrs, string $key, string $label ): string {
        $fallback = in_array( $key, self::APARTMENT_KEYS, true )
            ? __( '+ Add apartment, suite, etc.', 'yaycheckout' )
            /* translators: %s: field label, e.g. "company name". */
            : sprintf( __( '+ Add %s', 'yaycheckout' ), self::lower_first( $label ) );

        return LayoutMarkupReader::plain_text( $attrs, 'revealText', $fallback );
    }

    /** The link, then the opening of the hidden panel that holds the field. */
    public static function open( string $key, string $text ): string {
        return sprintf(
            '<button type="button" class="yaycheckout-reveal-link" aria-expanded="false" aria-controls="%1$s">%2$s</button><div class="yaycheckout-reveal-panel" id="%1$s" hidden>',
            esc_attr( $key . '-reveal-panel' ),
            esc_html( $text )
        );
    }

    /** Closes the panel open() started. */
    public static function close(): string {
        return '</div>';
    }

    /** "Company name" reads "company name" after "+ Add", but "VAT number" keeps its capitals. */
    private static function lower_first( string $label ): string {
        return preg_match( '/^[A-Z](?![A-Z])/', $label ) ? lcfirst( $label ) : $label;
    }
}
