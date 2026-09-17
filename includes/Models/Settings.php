<?php
namespace YayCheckout\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings. Pure entity: no WordPress calls.
 *
 * The schema() is the single description of every setting; from_option() and
 * sanitize() both walk it, so nothing is accepted without a default or
 * defaulted without validation.
 */
class Settings {

    // uninstall.php repeats OPTION_SETTINGS as a literal: it runs without the autoloader.
    public const OPTION_SETTINGS   = 'yaycheckout_settings';
    public const OPTION_UPDATED_AT = 'yaycheckout_settings_updated_at';
    public const OPTION_REVIEWED   = 'yaycheckout_reviewed';

    /** Minutes. Mirrored by PREVIEW_LINK_LIFETIME_OPTIONS in the SPA. */
    public const PREVIEW_LINK_LIFETIMES = [ 15, 30, 60, 120, 1440 ];

    public array $general  = [];
    public array $advanced = [];

    /** ISO 8601 UTC; null when never saved. */
    public ?string $updated_at = null;

    /**
     * Shape: section => key => [ 'type' => 'bool'|'int', 'default' => mixed, 'enum' => int[] (optional) ]
     */
    public static function schema(): array {
        return [
            'general'  => [
                'safe_mode'                => [
					'type'    => 'bool',
					'default' => false,
				],
                'share_usage_data'         => [
					'type'    => 'bool',
					'default' => false,
                ],
                'delete_data_on_uninstall' => [
					'type'    => 'bool',
					'default' => false,
                ],
            ],
            'advanced' => [
                'preview_link_lifetime'          => [
					'type'    => 'int',
					'default' => 60,
					'enum'    => self::PREVIEW_LINK_LIFETIMES,
				],
                'force_enqueue_checkout_scripts' => [
					'type'    => 'bool',
					'default' => false,
                ],
            ],
        ];
    }

    /**
     * Builds from the stored option value, projected through the schema.
     * Walks the schema, not the stored array: a stale or invalid stored value
     * reads back as its default instead of failing the SPA's strict wire schema.
     *
     * @param mixed $saved Raw option value; anything but an array is treated as empty.
     */
    public static function from_option( $saved, ?string $updated_at ): self {
        $saved    = is_array( $saved ) ? $saved : [];
        $settings = new self();

        foreach ( self::schema() as $section => $keys ) {
            foreach ( $keys as $key => $rule ) {
                $value = $saved[ $section ][ $key ] ?? null;

                $settings->{$section}[ $key ] = self::is_valid( $value, $rule ) ? $value : $rule['default'];
            }
        }

        $settings->updated_at = $updated_at;

        return $settings;
    }

    /**
     * Whitelists and type-checks an incoming settings array against the schema.
     * Unknown keys are dropped; types are strict (a string "true" is a bug,
     * not coerced); a missing key becomes its default, so partial updates
     * must merge with stored values first.
     *
     * @return array|\WP_Error Clean settings, or the first validation failure.
     */
    public static function sanitize( array $input ) {
        $clean = [];

        foreach ( self::schema() as $section => $keys ) {
            if ( array_key_exists( $section, $input ) && ! is_array( $input[ $section ] ) ) {
                return self::invalid( $section );
            }

            foreach ( $keys as $key => $rule ) {
                if ( ! isset( $input[ $section ] ) || ! array_key_exists( $key, $input[ $section ] ) ) {
                    $clean[ $section ][ $key ] = $rule['default'];
                    continue;
                }

                $value = $input[ $section ][ $key ];

                if ( ! self::is_valid( $value, $rule ) ) {
                    return self::invalid( $section . '.' . $key );
                }

                $clean[ $section ][ $key ] = $value;
            }
        }

        return $clean;
    }

    /** The shape both GET/POST /settings and the localize bootstrap emit. */
    public function to_array(): array {
        return [
            'settings'   => [
                'general'  => $this->general,
                'advanced' => $this->advanced,
            ],
            'updated_at' => $this->updated_at,
        ];
    }

    /** What sanitize() takes and the option stores. */
    public function values(): array {
        return $this->to_array()['settings'];
    }

    private static function is_valid( $value, array $rule ): bool {
        $valid = 'bool' === $rule['type'] ? is_bool( $value ) : is_int( $value );

        if ( $valid && isset( $rule['enum'] ) ) {
            $valid = in_array( $value, $rule['enum'], true );
        }

        return $valid;
    }

    private static function invalid( string $path ): \WP_Error {
        return new \WP_Error(
            'invalid_arguments',
            /* translators: %s: dotted path of the setting, e.g. advanced.preview_link_lifetime */
            sprintf( __( 'Invalid value for %s.', 'yaycheckout' ), $path )
        );
    }
}
