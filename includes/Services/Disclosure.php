<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * How a Field, Extra field or Coupon form hides until the shopper asks for it: behind a one-way
 * "+ Add …" link, in a section a header opens and closes, or behind a checkbox that shows it while ticked.
 * Pure: no storage, no hooks. packages/blocks/src/shared/disclosure.tsx mirrors the rules.
 */
class Disclosure {

    public const NONE        = 'none';
    public const LINK        = 'link';
    public const COLLAPSIBLE = 'collapsible';
    public const CHECKBOX    = 'checkbox';

    /** Behind a link unless the merchant says otherwise; every other field is shown unless asked. */
    public const APARTMENT_KEYS = [ 'billing_address_2', 'shipping_address_2' ];

    /**
     * How the field prints. A required field is always shown, so checkout never fails on a field
     * the shopper cannot see, and so is one already holding the shopper's value, so a reload doesn't
     * hide it. The editor has no value, so disclosure.tsx has no such rule.
     *
     * @param array $attrs Raw block attributes.
     * @param mixed $value The value the field prints; null, '' and [] are empty.
     */
    public static function mode( array $attrs, string $key, bool $required, $value = null ): string {
        if ( $required || ! in_array( $value, [ null, '', [] ], true ) ) {
            return self::NONE;
        }

        if ( isset( $attrs['disclosure'] ) && in_array( $attrs['disclosure'], [ self::NONE, self::LINK, self::COLLAPSIBLE, self::CHECKBOX ], true ) ) {
            return $attrs['disclosure'];
        }

        return in_array( $key, self::APARTMENT_KEYS, true ) ? self::LINK : self::NONE;
    }

    /**
     * The toggle's text: the merchant's, or by mode "+ Add" and the label, "Add" and the label, or the label alone.
     *
     * @param array  $attrs Raw block attributes.
     * @param string $label The field's label as plain text.
     */
    public static function text( array $attrs, string $key, string $label, string $mode ): string {
        if ( self::COLLAPSIBLE === $mode ) {
            $fallback = $label;
        } elseif ( self::CHECKBOX === $mode && in_array( $key, self::APARTMENT_KEYS, true ) ) {
            $fallback = __( 'Add apartment, suite, etc.', 'yaycheckout' );
        } elseif ( self::CHECKBOX === $mode ) {
            /* translators: %s: field label, e.g. "company name". */
            $fallback = sprintf( __( 'Add %s', 'yaycheckout' ), self::lower_first( $label ) );
        } elseif ( in_array( $key, self::APARTMENT_KEYS, true ) ) {
            $fallback = __( '+ Add apartment, suite, etc.', 'yaycheckout' );
        } else {
            /* translators: %s: field label, e.g. "company name". */
            $fallback = sprintf( __( '+ Add %s', 'yaycheckout' ), self::lower_first( $label ) );
        }

        return LayoutMarkupReader::plain_text( $attrs, 'disclosureText', $fallback );
    }

    /** The toggle, then the opening of the hidden panel that holds the field. */
    public static function open( string $key, string $text, string $mode ): string {
        // No name, so the checkbox never posts with the checkout form.
        if ( self::CHECKBOX === $mode ) {
            return sprintf(
                '<label class="yaycheckout-disclosure__toggle" data-mode="%1$s"><input type="checkbox" class="yaycheckout-disclosure__checkbox" aria-controls="%2$s" /><span>%3$s</span></label><div class="yaycheckout-disclosure__panel" id="%2$s" hidden>',
                esc_attr( $mode ),
                esc_attr( $key . '-disclosure-panel' ),
                esc_html( $text )
            );
        }

        $chevron = self::COLLAPSIBLE === $mode
            ? '<svg class="yaycheckout-disclosure__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M17.5 11.6 12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2Z"/></svg>'
            : '';

        return sprintf(
            '<button type="button" class="yaycheckout-disclosure__toggle" data-mode="%1$s" aria-expanded="false" aria-controls="%2$s"><span>%3$s</span>%4$s</button><div class="yaycheckout-disclosure__panel" id="%2$s" hidden>',
            esc_attr( $mode ),
            esc_attr( $key . '-disclosure-panel' ),
            esc_html( $text ),
            $chevron
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
