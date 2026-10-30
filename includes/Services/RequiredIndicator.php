<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * The label suffix that marks a field required or optional: the layout's requiredMark /
 * optionalText, or WooCommerce's * and (optional) when empty. Pure: no storage, no hooks.
 */
class RequiredIndicator {

    /** The tags html() prints, for the wp_kses() a render.php runs at the echo. */
    public const ALLOWED_HTML = [
        'span' => [
            'class'       => true,
            'aria-hidden' => true,
        ],
    ];

    /**
     * Suffix for block templates that print their own label.
     *
     * @param array $attrs Raw block attributes, so strings are trimmed here.
     */
    public static function html( bool $required, array $attrs ): string {
        return '&nbsp;' . self::span( $required, trim( LayoutMarkupReader::text( $attrs, $required ? 'requiredMark' : 'optionalText' ) ) );
    }

    /**
     * WooCommerce field markup with its suffix replaced by the layout's string.
     * Left untouched when the layout has no entry or no string for this state.
     *
     * @param  mixed $field         woocommerce_form_field output.
     * @param  array $args          The field's woocommerce_form_field args.
     * @param  array $layout_fields LayoutMarkupReader fields and extra fields, by key.
     * @return mixed
     */
    public static function swap_in( $field, string $key, array $args, array $layout_fields ) {
        $required = ! empty( $args['required'] );
        $layout   = $layout_fields[ $key ] ?? null;
        $text     = is_array( $layout ) ? LayoutMarkupReader::text( $layout, $required ? 'requiredMark' : 'optionalText' ) : '';

        if ( ! is_string( $field ) || '' === $text ) {
            return $field;
        }

        $replaced = preg_replace_callback(
            sprintf( '/<span class="%s"[^>]*>.*?<\/span>/s', $required ? 'required' : 'optional' ),
            static fn() => self::span( $required, $text ),
            $field,
            1
        );

        return is_string( $replaced ) ? $replaced : $field;
    }

    /**
     * Required flag and strings per field key, for the storefront script that
     * re-applies them after WooCommerce's address-i18n.js resets labels.
     *
     * @param  array $layout_fields LayoutMarkupReader fields and extra fields, by key.
     * @return array<string, array{required: bool, requiredMark: string, optionalText: string}>
     */
    public static function script_payload( array $layout_fields ): array {
        $payload = [];

        foreach ( $layout_fields as $key => $layout ) {
            $payload[ $key ] = [
                'required'     => RequiredFieldOverlay::is_required( (string) $key, $layout ),
                'requiredMark' => LayoutMarkupReader::text( $layout, 'requiredMark' ),
                'optionalText' => LayoutMarkupReader::text( $layout, 'optionalText' ),
            ];
        }

        return $payload;
    }

    /** The suffix span; an empty $text falls back to WooCommerce's * or (optional). */
    private static function span( bool $required, string $text ): string {
        if ( $required ) {
            return '<span class="required" aria-hidden="true">' . esc_html( '' === $text ? '*' : $text ) . '</span>';
        }

        return '<span class="optional">(' . esc_html( '' === $text ? 'optional' : $text ) . ')</span>';
    }
}
