<?php
namespace YayCheckout\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings. schema() describes every setting; from_option() and sanitize()
 * both walk it, so nothing is accepted without a default or defaulted without validation.
 */
class Settings {

    // uninstall.php repeats OPTION_SETTINGS as a literal: it runs without the autoloader.
    public const OPTION_SETTINGS   = 'yaycheckout_settings';
    public const OPTION_UPDATED_AT = 'yaycheckout_settings_updated_at';
    public const OPTION_REVIEWED   = 'yaycheckout_reviewed';
    public const OPTION_SETUP_DONE = 'yaycheckout_setup_done';

    /** Minutes. Mirrored by PREVIEW_LINK_LIFETIME_OPTIONS in the SPA. */
    public const PREVIEW_LINK_LIFETIMES = [ 15, 30, 60, 120, 1440 ];

    public array $general  = [];
    public array $advanced = [];

    /** ISO 8601 UTC; null when never saved. */
    public ?string $updated_at = null;

    /**
     * Shape: section => key => [ 'type' => 'bool'|'int'|'css', 'default' => mixed, 'enum' => int[] (optional) ]
     *
     * A `css` value is a string LayoutDesign::sanitize_custom_css() accepts unchanged, so a stored value it rejects
     * reads as '' and can't block saving the other settings. sanitize() refuses one with that check's message.
     */
    public static function schema(): array {
        return [
            'general'  => [
                // Read by Renderer\CheckoutPage: on, a layout with a problem gives way to WooCommerce's checkout.
                'safe_mode'                => [
					'type'    => 'bool',
					'default' => true,
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
                // Read by Renderer\CheckoutBaseStyles: printed on every checkout, before the layout's own custom CSS.
                'custom_css'                     => [
					'type'    => 'css',
					'default' => '',
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

                // A refused CSS says why, as the layout editor does.
                if ( 'css' === $rule['type'] && is_string( $value ) ) {
                    $css = LayoutDesign::sanitize_custom_css( $value );

                    if ( $css instanceof \WP_Error ) {
                        return $css;
                    }
                }

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
        if ( 'css' === $rule['type'] ) {
            return is_string( $value ) && LayoutDesign::sanitize_custom_css( $value ) === $value;
        }

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
