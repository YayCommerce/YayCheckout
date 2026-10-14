<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Data printed for a storefront script as a JSON element, which update_order_review can swap as a fragment.
 * Read by packages/blocks/src/shared/json-element.ts.
 */
class JsonElementHelper {

    /**
     * `<script type="application/json" id="$id">` holding $data. `<` and `&` are escaped, so no value can close it.
     *
     * @param mixed $data Anything wp_json_encode() takes.
     */
    public static function html( string $id, $data ): string {
        return '<script type="application/json" id="' . esc_attr( $id ) . '">'
            . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP )
            . '</script>';
    }
}
