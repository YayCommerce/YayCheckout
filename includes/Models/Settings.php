<?php
namespace YayCheckout\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings.
 *
 * Dumb entity, like Layout: it holds the option-key constants, the schema,
 * and the pure rules for reading and validating a settings array, and makes
 * no WordPress calls. SettingsRepository hands it already-fetched data.
 *
 * schema() is the one description of every setting: section, key, type,
 * default and (for enums) the allowed values. from_option() and sanitize()
 * both walk it, so a setting cannot be accepted without a default or
 * defaulted without validation. Adding a setting is one entry here, plus the
 * wire/app zod schema and a form field in the SPA.
 */
class Settings {

    /*
     * Option names live here and nowhere else — the same rule as Layout's
     * meta keys. uninstall.php is the documented exception: it runs without
     * the autoloader and repeats OPTION_SETTINGS as a literal.
     */
    public const OPTION_SETTINGS   = 'yaycheckout_settings';
    public const OPTION_UPDATED_AT = 'yaycheckout_settings_updated_at';
    public const OPTION_REVIEWED   = 'yaycheckout_reviewed';

    /** Minutes a shared preview URL stays valid. Mirrored by PREVIEW_LINK_LIFETIME_OPTIONS in the SPA. */
    public const PREVIEW_LINK_LIFETIMES = [ 15, 30, 60, 120, 1440 ];

    public array $general  = [];
    public array $advanced = [];

    /** ISO 8601 UTC of the last save, or null when settings were never saved. */
    public ?string $updated_at = null;

    /**
     * section => key => [ 'type' => 'bool'|'int', 'default' => mixed, 'enum' => int[] (optional) ]
     */
    public static function schema(): array {
        return [
            'general'  => [
                'safe_mode'                => [ 'type' => 'bool', 'default' => false ],
                'share_usage_data'         => [ 'type' => 'bool', 'default' => false ],
                'delete_data_on_uninstall' => [ 'type' => 'bool', 'default' => false ],
            ],
            'advanced' => [
                'preview_link_lifetime'          => [ 'type' => 'int', 'default' => 60, 'enum' => self::PREVIEW_LINK_LIFETIMES ],
                'force_enqueue_checkout_scripts' => [ 'type' => 'bool', 'default' => false ],
            ],
        ];
    }

    /**
     * Build from the stored option value, projected through the schema.
     *
     * Walks the schema rather than merging blindly, so a key that no longer
     * exists, or a stored value that is no longer valid (written by an older
     * version, or edited by hand), reads back as its default instead of
     * reaching the SPA, whose strict wire schema would refuse the payload.
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
     * Whitelist and type-check an incoming settings array against the schema.
     *
     * Walks the schema rather than the input, so unknown sections and keys
     * are dropped silently. Types are strict: the only client is the SPA,
     * which sends typed JSON, so a string "true" means a bug and is rejected
     * rather than coerced. A key missing from the input falls back to its
     * default — callers wanting a partial update merge with the stored
     * values first.
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

    /** The settings object alone — what sanitize() takes and the option stores. */
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
