<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Posted name for an extra-field block.
 *
 * Render and the layout block index must invent the same fallback when the
 * merchant left Key empty, otherwise checkout posts yaycheckout_extra_1 and
 * persist looks for nothing.
 */
class ExtraFieldKeyHelper {

    private static int $unnamed = 0;

    public static function reset(): void {
        self::$unnamed = 0;
    }

    /**
     * @param  array $attrs Block attributes.
     * @return string
     */
    public static function from_attributes( array $attrs ): string {
        $key = isset( $attrs['fieldKey'] ) ? trim( (string) $attrs['fieldKey'] ) : '';

        if ( '' !== $key ) {
            return $key;
        }

        ++self::$unnamed;

        return 'yaycheckout_extra_' . self::$unnamed;
    }
}
