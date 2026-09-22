<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Overlays layout required flags onto WooCommerce's checkout field registry.
 *
 * Pure: no storage, no hooks. Email stays required so checkout always has a
 * way to reach the customer. See docs/system-architecture.md, Validation.
 */
class CheckoutFieldRequirements {

    private const ADDRESS_FIELDSETS = [ 'billing', 'shipping' ];

    /**
     * @param  array $fields        WooCommerce checkout fields.
     * @param  array $layout_fields LayoutBlockIndex `fields` map.
     * @return array
     */
    public static function apply( array $fields, array $layout_fields ): array {
        foreach ( self::ADDRESS_FIELDSETS as $fieldset ) {
            if ( ! isset( $fields[ $fieldset ] ) || ! is_array( $fields[ $fieldset ] ) ) {
                $fields[ $fieldset ] = [];
            }

            foreach ( $fields[ $fieldset ] as $key => $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }

                $layout                                  = self::layout_field( $layout_fields, (string) $key );
                $fields[ $fieldset ][ $key ]['required'] = self::is_required( (string) $key, $field, $layout );
            }

            foreach ( $layout_fields as $key => $layout ) {
                if ( ! is_array( $layout ) ) {
                    continue;
                }

                $key = isset( $layout['fieldKey'] ) ? trim( (string) $layout['fieldKey'] ) : trim( (string) $key );

                if ( '' === $key || ! self::key_belongs_to( $key, $fieldset ) || isset( $fields[ $fieldset ][ $key ] ) ) {
                    continue;
                }

                $fields[ $fieldset ][ $key ] = self::definition_from_layout( $key, $layout );
            }
        }

        return $fields;
    }

    /**
     * Field keys mapped to required + indicator strings, for the storefront
     * script that undoes address-i18n.js.
     *
     * @param  array $layout_fields
     * @return array<string, array{required: bool, requiredMark: string, optionalText: string}>
     */
    public static function required_map( array $layout_fields ): array {
        $map = [];

        foreach ( $layout_fields as $key => $layout ) {
            if ( ! is_array( $layout ) ) {
                continue;
            }

            $key = isset( $layout['fieldKey'] ) ? trim( (string) $layout['fieldKey'] ) : trim( (string) $key );

            if ( '' === $key ) {
                continue;
            }

            $map[ $key ] = [
                'required'     => self::is_required( $key, [], $layout ),
                'requiredMark' => self::indicator_string( $layout, 'requiredMark' ),
                'optionalText' => self::indicator_string( $layout, 'optionalText' ),
            ];
        }

        return $map;
    }

    /**
     * Swaps WooCommerce's * / (optional) for the layout's strings. Unknown keys
     * and empty strings are left alone so stock markup stays the default.
     *
     * @param  mixed  $field
     * @param  string $key
     * @param  array  $args
     * @param  array  $layout_fields
     * @return mixed
     */
    public static function replace_form_field( $field, string $key, array $args, array $layout_fields ) {
        if ( ! is_string( $field ) || '' === $field ) {
            return $field;
        }

        $layout = self::layout_field( $layout_fields, $key );

        if ( null === $layout ) {
            return $field;
        }

        if ( ! empty( $args['required'] ) ) {
            $mark = self::indicator_string( $layout, 'requiredMark' );

            if ( '' === $mark ) {
                return $field;
            }

            return self::replace_span( $field, 'required', esc_html( $mark ) );
        }

        $text = self::indicator_string( $layout, 'optionalText' );

        if ( '' === $text ) {
            return $field;
        }

        return self::replace_span( $field, 'optional', '(' . esc_html( $text ) . ')' );
    }

    /**
     * Label suffix when woocommerce_form_field is unavailable. Empty layout
     * strings fall back to WooCommerce's * and (optional).
     */
    public static function indicator_html( bool $required, array $layout ): string {
        if ( $required ) {
            $mark = self::indicator_string( $layout, 'requiredMark' );

            if ( '' === $mark ) {
                $mark = '*';
            }

            return '&nbsp;<span class="required" aria-hidden="true">' . esc_html( $mark ) . '</span>';
        }

        $text = self::indicator_string( $layout, 'optionalText' );

        if ( '' === $text ) {
            $text = 'optional';
        }

        return '&nbsp;<span class="optional">(' . esc_html( $text ) . ')</span>';
    }

    /**
     * @param  array  $layout_fields
     * @param  string $key
     * @return array|null
     */
    private static function layout_field( array $layout_fields, string $key ): ?array {
        if ( isset( $layout_fields[ $key ] ) && is_array( $layout_fields[ $key ] ) ) {
            return $layout_fields[ $key ];
        }

        foreach ( $layout_fields as $layout ) {
            if ( ! is_array( $layout ) ) {
                continue;
            }

            $layout_key = isset( $layout['fieldKey'] ) ? trim( (string) $layout['fieldKey'] ) : '';

            if ( $layout_key === $key ) {
                return $layout;
            }
        }

        return null;
    }

    /**
     * @param  string     $key
     * @param  array      $field
     * @param  array|null $layout
     * @return bool
     */
    private static function is_required( string $key, array $field, ?array $layout ): bool {
        if ( self::is_email_field( $key, $field, $layout ?? [] ) ) {
            return true;
        }

        if ( null === $layout ) {
            return false;
        }

        return ! empty( $layout['required'] );
    }

    /**
     * @param  string $key
     * @param  array  $field
     * @param  array  $layout
     * @return bool
     */
    private static function is_email_field( string $key, array $field, array $layout ): bool {
        if ( 'billing_email' === $key ) {
            return true;
        }

        $type = '';
        if ( isset( $layout['type'] ) && is_string( $layout['type'] ) ) {
            $type = $layout['type'];
        } elseif ( isset( $field['type'] ) && is_string( $field['type'] ) ) {
            $type = $field['type'];
        }

        return 'email' === $type;
    }

    private static function key_belongs_to( string $key, string $fieldset ): bool {
        return strpos( $key, $fieldset . '_' ) === 0;
    }

    /**
     * @param  string $key
     * @param  array  $layout
     * @return array
     */
    private static function definition_from_layout( string $key, array $layout ): array {
        $type  = isset( $layout['type'] ) && is_string( $layout['type'] ) && '' !== $layout['type'] ? $layout['type'] : 'text';
        $label = isset( $layout['label'] ) && is_string( $layout['label'] ) && '' !== $layout['label'] ? $layout['label'] : $key;

        $definition = [
            'type'     => $type,
            'label'    => $label,
            'required' => self::is_required( $key, [], $layout ),
            'validate' => self::validate_for( $key, $type, $layout ),
        ];

        if ( isset( $layout['placeholder'] ) && is_string( $layout['placeholder'] ) && '' !== $layout['placeholder'] ) {
            $definition['placeholder'] = $layout['placeholder'];
        }

        if ( isset( $layout['autocomplete'] ) && is_string( $layout['autocomplete'] ) && '' !== $layout['autocomplete'] ) {
            $definition['autocomplete'] = $layout['autocomplete'];
        }

        return $definition;
    }

    /**
     * @param  string $key
     * @param  string $type
     * @param  array  $layout
     * @return string[]
     */
    private static function validate_for( string $key, string $type, array $layout ): array {
        if ( isset( $layout['validate'] ) && is_array( $layout['validate'] ) && [] !== $layout['validate'] ) {
            return array_values( array_map( 'strval', $layout['validate'] ) );
        }

        $rules = [];

        if ( 'email' === $type || 'billing_email' === $key ) {
            $rules[] = 'email';
        }

        if ( 'tel' === $type || substr( $key, -6 ) === '_phone' ) {
            $rules[] = 'phone';
        }

        if ( 'state' === $type || substr( $key, -6 ) === '_state' ) {
            $rules[] = 'state';
        }

        if ( substr( $key, -9 ) === '_postcode' ) {
            $rules[] = 'postcode';
        }

        return $rules;
    }

    private static function indicator_string( array $layout, string $key ): string {
        return isset( $layout[ $key ] ) && is_string( $layout[ $key ] ) ? trim( $layout[ $key ] ) : '';
    }

    /**
     * @param  string $field
     * @param  string $span_class required or optional.
     * @param  string $inner      Already escaped.
     * @return string
     */
    private static function replace_span( string $field, string $span_class, string $inner ): string {
        $pattern = sprintf( '/<span class="%s"[^>]*>.*?<\/span>/s', preg_quote( $span_class, '/' ) );

        $replaced = preg_replace_callback(
            $pattern,
            static function () use ( $span_class, $inner ) {
                $attrs = 'required' === $span_class ? ' class="required" aria-hidden="true"' : ' class="optional"';

                return '<span' . $attrs . '>' . $inner . '</span>';
            },
            $field,
            1
        );

        return is_string( $replaced ) ? $replaced : $field;
    }
}
