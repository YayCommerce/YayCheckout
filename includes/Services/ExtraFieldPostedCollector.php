<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Reads posted extra-field values against the extra_fields map from the layout.
 *
 * Pure: no storage, no Woo hooks. Checkout only persists keys this returns,
 * so a crafted POST cannot write arbitrary order meta.
 */
class ExtraFieldPostedCollector {

    /**
     * @param  array $fields extra_fields map from LayoutMarkupReader::extra_fields().
     * @param  array $posted Request payload (typically unslashed $_POST).
     * @return array{rows: array<int, array>, errors: array<int, array{key: string, code: string}>}
     */
    public static function collect( array $fields, array $posted ): array {
        $rows   = [];
        $errors = [];

        foreach ( $fields as $key => $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $key = isset( $field['fieldKey'] ) ? trim( (string) $field['fieldKey'] ) : trim( (string) $key );

            if ( '' === $key ) {
                continue;
            }

            $type     = isset( $field['type'] ) && is_string( $field['type'] ) && '' !== $field['type'] ? $field['type'] : 'text';
            $label    = isset( $field['label'] ) && is_string( $field['label'] ) && '' !== $field['label'] ? $field['label'] : $key;
            $required = ! empty( $field['required'] );
            $options  = self::option_map( isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : [] );
            $raw      = array_key_exists( $key, $posted ) ? $posted[ $key ] : null;
            $row      = self::normalize( $type, $raw, $options );

            if ( null === $row ) {
                if ( $required ) {
                    $errors[] = [
                        'key'  => $key,
                        'code' => 'required',
                    ];
                }
                continue;
            }

            if ( false === $row ) {
                $errors[] = [
                    'key'  => $key,
                    'code' => 'invalid',
                ];
                continue;
            }

            $rows[] = [
                'key'     => $key,
                'label'   => $label,
                'type'    => $type,
                'value'   => $row['value'],
                'display' => $row['display'],
            ];
        }

        return [
            'rows'   => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array $options
     * @return array<string, string> value => label
     */
    private static function option_map( array $options ): array {
        $map = [];

        foreach ( $options as $option ) {
            if ( ! is_array( $option ) ) {
                continue;
            }

            $value = isset( $option['value'] ) ? (string) $option['value'] : '';
            $label = isset( $option['label'] ) ? (string) $option['label'] : $value;

            if ( '' === $value && '' === $label ) {
                continue;
            }

            if ( '' === $value ) {
                $value = self::title_key( $label );
            }

            $map[ $value ] = '' !== $label ? $label : $value;
        }

        return $map;
    }

    /**
     * @param  string $type
     * @param  mixed  $raw
     * @param  array  $options
     * @return array{value: mixed, display: string}|null|false Null = empty, false = invalid.
     */
    private static function normalize( string $type, $raw, array $options ) {
        if ( 'checkbox' === $type && ! empty( $options ) ) {
            return self::normalize_choices( is_array( $raw ) ? $raw : [], $options );
        }

        if ( 'checkbox' === $type ) {
            return self::normalize_single_checkbox( $raw );
        }

        if ( in_array( $type, [ 'select', 'radio' ], true ) ) {
            return self::normalize_choice( $raw, $options );
        }

        return self::normalize_text( $raw );
    }

    /**
     * @param  mixed $raw
     * @return array{value: string, display: string}|null
     */
    private static function normalize_text( $raw ) {
        if ( is_array( $raw ) ) {
            $raw = '';
        }

        $value = self::sanitize_text( (string) $raw );

        if ( '' === $value ) {
            return null;
        }

        return [
            'value'   => $value,
            'display' => $value,
        ];
    }

    /**
     * @param  mixed $raw
     * @return array{value: string, display: string}|null
     */
    private static function normalize_single_checkbox( $raw ) {
        if ( is_array( $raw ) || null === $raw || '' === $raw || '0' === $raw || false === $raw ) {
            return null;
        }

        $yes = function_exists( '__' ) ? __( 'Yes', 'yaycheckout' ) : 'Yes';

        return [
            'value'   => '1',
            'display' => $yes,
        ];
    }

    /**
     * @param  mixed $raw
     * @param  array $options
     * @return array{value: string, display: string}|null|false
     */
    private static function normalize_choice( $raw, array $options ) {
        if ( is_array( $raw ) ) {
            $raw = '';
        }

        $value = self::sanitize_text( (string) $raw );

        if ( '' === $value ) {
            return null;
        }

        if ( ! array_key_exists( $value, $options ) ) {
            return false;
        }

        return [
            'value'   => $value,
            'display' => $options[ $value ],
        ];
    }

    /**
     * @param  array $raw
     * @param  array $options
     * @return array{value: string[], display: string}|null|false
     */
    private static function normalize_choices( array $raw, array $options ) {
        $values  = [];
        $labels  = [];
        $invalid = false;

        foreach ( $raw as $item ) {
            if ( is_array( $item ) ) {
                $invalid = true;
                continue;
            }

            $value = self::sanitize_text( (string) $item );

            if ( '' === $value ) {
                continue;
            }

            if ( ! array_key_exists( $value, $options ) ) {
                $invalid = true;
                continue;
            }

            $values[] = $value;
            $labels[] = $options[ $value ];
        }

        if ( $invalid ) {
            return false;
        }

        if ( [] === $values ) {
            return null;
        }

        return [
            'value'   => $values,
            'display' => implode( ', ', $labels ),
        ];
    }

    private static function sanitize_text( string $value ): string {
        if ( function_exists( 'wp_unslash' ) ) {
            $value = wp_unslash( $value );
        }

        if ( function_exists( 'sanitize_text_field' ) ) {
            return sanitize_text_field( $value );
        }

        $value = preg_replace( '/<[^>]*>/', '', $value );
        $value = preg_replace( '/\s+/', ' ', is_string( $value ) ? $value : '' );

        return trim( is_string( $value ) ? $value : '' );
    }

    private static function title_key( string $label ): string {
        if ( function_exists( 'sanitize_title' ) ) {
            return sanitize_title( $label );
        }

        $key = strtolower( trim( $label ) );
        $key = preg_replace( '/[^a-z0-9]+/', '-', $key );

        return trim( is_string( $key ) ? $key : '', '-' );
    }
}
