<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a checkout submission into the extra-field rows to store and the errors to show.
 *
 * Pure: no storage, no Woo hooks. Only Keys in $fields are read and choice
 * values must be one of the field's options.
 */
class ExtraFieldSubmission {

    /**
     * @param  array $fields LayoutMarkupReader::extra_fields() of the served layout.
     * @param  array $posted Unslashed $_POST.
     * @return array{rows: array<int, array{key: string, label: string, type: string, value: mixed, display: string}>, errors: array<string, string>}
     */
    public static function collect( array $fields, array $posted ): array {
        $rows   = [];
        $errors = [];

        foreach ( $fields as $key => $field ) {
            $key   = (string) $key;
            $label = '' !== $field['label'] ? $field['label'] : $key;
            $value = self::value( $field, $posted[ $key ] ?? null );

            if ( false === $value ) {
                /* translators: %s: extra field label. */
                $errors[ $key ] = sprintf( __( '%s is not a valid selection.', 'yaycheckout' ), $label );
            } elseif ( null === $value ) {
                if ( $field['required'] ) {
                    /* translators: %s: extra field label. */
                    $errors[ $key ] = sprintf( __( '%s is a required field.', 'yaycheckout' ), $label );
                }
            } else {
                $rows[] = [
                    'key'     => $key,
                    'label'   => $label,
                    'type'    => $field['type'],
                    'value'   => $value['value'],
                    'display' => $value['display'],
                ];
            }
        }

        return [
            'rows'   => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array $field
     * @param  mixed $raw
     * @return array{value: mixed, display: string}|null|false Null = left empty, false = not an allowed value.
     */
    private static function value( array $field, $raw ) {
        $options = $field['options'];

        switch ( $field['type'] ) {
            case 'checkbox':
                return empty( $options ) ? self::single_checkbox( $raw ) : self::choices( $raw, $options );
            case 'select':
            case 'radio':
                return self::choice( $raw, $options );
            default:
                return self::text( $raw );
        }
    }

    /**
     * @param  mixed $raw
     * @return array{value: string, display: string}|null
     */
    private static function text( $raw ): ?array {
        $value = self::clean( $raw );

        return '' === $value ? null : [
            'value'   => $value,
            'display' => $value,
        ];
    }

    /**
     * A lone box: ticked reads as Yes, unticked or '0' as left empty.
     *
     * @param  mixed $raw
     * @return array{value: string, display: string}|null
     */
    private static function single_checkbox( $raw ): ?array {
        if ( is_array( $raw ) || in_array( $raw, [ null, '', '0', false ], true ) ) {
            return null;
        }

        return [
            'value'   => '1',
            'display' => __( 'Yes', 'yaycheckout' ),
        ];
    }

    /**
     * Select and radio: one value, which must be an option.
     *
     * @param  mixed                 $raw
     * @param  array<string, string> $options
     * @return array{value: string, display: string}|null|false
     */
    private static function choice( $raw, array $options ) {
        $value = self::clean( $raw );

        if ( '' === $value ) {
            return null;
        }

        return isset( $options[ $value ] ) ? [
            'value'   => $value,
            'display' => $options[ $value ],
        ] : false;
    }

    /**
     * Checkbox group: every ticked value must be an option, or the whole group is invalid.
     *
     * @param  mixed                 $raw
     * @param  array<string, string> $options
     * @return array{value: string[], display: string}|null|false
     */
    private static function choices( $raw, array $options ) {
        $values = [];

        foreach ( is_array( $raw ) ? $raw : [] as $item ) {
            if ( is_array( $item ) ) {
                return false;
            }

            $value = self::clean( $item );

            if ( '' === $value ) {
                continue;
            }

            if ( ! isset( $options[ $value ] ) ) {
                return false;
            }

            $values[] = $value;
        }

        if ( [] === $values ) {
            return null;
        }

        return [
            'value'   => $values,
            'display' => implode( ', ', array_map( static fn( $value ) => $options[ $value ], $values ) ),
        ];
    }

    /**
     * @param mixed $raw A posted scalar; arrays read as empty.
     */
    private static function clean( $raw ): string {
        return is_array( $raw ) ? '' : sanitize_text_field( (string) $raw );
    }
}
