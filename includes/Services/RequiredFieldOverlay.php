<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Makes WooCommerce's checkout field registry follow the layout's required flags.
 *
 * Pure: no storage, no hooks.
 */
class RequiredFieldOverlay {

    private const ADDRESS_FIELDSETS = [ 'billing', 'shipping' ];

    /**
     * Address fields required exactly when the layout says so. A field the
     * layout leaves out becomes optional; a layout field WooCommerce dropped
     * is added back.
     *
     * @param  array $fields        WooCommerce checkout fields, by fieldset.
     * @param  array $layout_fields LayoutMarkupReader::fields() map.
     * @return array
     */
    public static function apply( array $fields, array $layout_fields ): array {
        foreach ( self::ADDRESS_FIELDSETS as $fieldset ) {
            $fields[ $fieldset ] = isset( $fields[ $fieldset ] ) && is_array( $fields[ $fieldset ] ) ? $fields[ $fieldset ] : [];

            foreach ( $fields[ $fieldset ] as $key => $field ) {
                if ( is_array( $field ) ) {
                    $fields[ $fieldset ][ $key ]['required'] = self::is_required( (string) $key, $layout_fields[ $key ] ?? null, $field );
                }
            }

            foreach ( $layout_fields as $key => $layout ) {
                $key = (string) $key;

                if ( str_starts_with( $key, $fieldset . '_' ) && ! isset( $fields[ $fieldset ][ $key ] ) ) {
                    $fields[ $fieldset ][ $key ] = self::restore_missing_field( $key, $layout );
                }
            }
        }

        return $fields;
    }

    /**
     * Whether the field is required: always for billing_email or an email-type
     * field, the only way to reach the customer; otherwise only when the layout marks it.
     *
     * @param array|null $layout   The field's layout entry; null when the layout leaves it out.
     * @param array      $wc_field WooCommerce's definition; its type counts only when the layout has no entry.
     */
    public static function is_required( string $key, ?array $layout, array $wc_field = [] ): bool {
        $type = $layout['type'] ?? $wc_field['type'] ?? '';

        if ( 'billing_email' === $key || 'email' === $type ) {
            return true;
        }

        return ! empty( $layout['required'] );
    }

    /**
     * A WooCommerce definition built from the layout. The Customizer's
     * "Hidden" setting removes a field from the registry; without it back,
     * WooCommerce never validates or saves a field the layout still renders.
     */
    private static function restore_missing_field( string $key, array $layout ): array {
        $type = self::filled( $layout, 'type', 'text' );

        $definition = [
            'type'     => $type,
            'label'    => self::filled( $layout, 'label', $key ),
            'required' => self::is_required( $key, $layout ),
            'validate' => ! empty( $layout['validate'] ) && is_array( $layout['validate'] ) ? $layout['validate'] : self::validate_rules( $key, $type ),
        ];

        foreach ( [ 'placeholder', 'autocomplete' ] as $attr ) {
            if ( '' !== self::filled( $layout, $attr, '' ) ) {
                $definition[ $attr ] = $layout[ $attr ];
            }
        }

        return $definition;
    }

    /**
     * WooCommerce validate rules a stock field with this key or type carries.
     *
     * @return string[]
     */
    private static function validate_rules( string $key, string $type ): array {
        $rules = [];

        if ( 'email' === $type || 'billing_email' === $key ) {
            $rules[] = 'email';
        }

        if ( 'tel' === $type || str_ends_with( $key, '_phone' ) ) {
            $rules[] = 'phone';
        }

        if ( 'state' === $type || str_ends_with( $key, '_state' ) ) {
            $rules[] = 'state';
        }

        if ( str_ends_with( $key, '_postcode' ) ) {
            $rules[] = 'postcode';
        }

        return $rules;
    }

    /** A non-empty string attribute, or $fallback. */
    private static function filled( array $layout, string $name, string $fallback ): string {
        return isset( $layout[ $name ] ) && is_string( $layout[ $name ] ) && '' !== $layout[ $name ] ? $layout[ $name ] : $fallback;
    }
}
