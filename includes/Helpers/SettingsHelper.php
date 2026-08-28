<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage — single option, merged with defaults.
 */
class SettingsHelper {

    public const OPTION_SETTINGS = 'yaycheckout_settings';
    public const OPTION_REVIEWED = 'yaycheckout_reviewed';

    public static function get_default_settings(): array {
        return [
            'general' => [
                'enabled' => true,
            ],
        ];
    }

    public static function get_settings(): array {
        $saved = get_option( self::OPTION_SETTINGS, [] );

        if ( ! is_array( $saved ) ) {
            $saved = [];
        }

        return array_replace_recursive( self::get_default_settings(), $saved );
    }

    public static function update_settings( array $settings ): array {
        update_option( self::OPTION_SETTINGS, $settings );
        return self::get_settings();
    }
}
