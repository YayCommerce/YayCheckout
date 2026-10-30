<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Data printed for a storefront script as a JSON element, which update_order_review can swap as a fragment.
 * Built by core's script tag API. Read by packages/blocks/src/shared/json-element.ts.
 */
class JsonElementHelper {

    /**
     * `<script type="application/json" id="$id">` holding $data, as a string for a fragment.
     *
     * @param mixed $data Anything wp_json_encode() takes.
     */
    public static function html( string $id, $data ): string {
        return wp_get_inline_script_tag( self::json( $data ), self::attributes( $id ) );
    }

    /**
     * Prints the same element as html().
     *
     * @param mixed $data Anything wp_json_encode() takes.
     */
    public static function print( string $id, $data ): void {
        wp_print_inline_script_tag( self::json( $data ), self::attributes( $id ) );
    }

    /**
     * $data as JSON with `<`, `>` and `&` escaped, so no value can close the element. Older core, from the
     * 6.5 floor, puts the text in the tag as given; core's Interactivity API uses the same flags.
     *
     * @param mixed $data Anything wp_json_encode() takes.
     */
    private static function json( $data ): string {
        return (string) wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP );
    }

    private static function attributes( string $id ): array {
        return [
            'type' => 'application/json',
            'id'   => $id,
        ];
    }
}
