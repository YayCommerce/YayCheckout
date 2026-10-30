<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Allow-lists for wp_kses() where the storefront echoes HTML it assembled from WooCommerce's
 * filtered output: prices, rate labels, cart thumbnails and the coupon chips.
 */
class KsesHelper {

    /**
     * Post content's tags, plus what those pieces carry and post content lacks: wc_price()'s <bdi> and
     * its currency symbol's translate="no", a product image's responsive attributes, and the inline SVG
     * icons the chips print.
     */
    public static function storefront_html(): array {
        static $tags = null;

        if ( null !== $tags ) {
            return $tags;
        }

        $tags = wp_kses_allowed_html( 'post' );

        $tags['span']['translate'] = true;

        $tags['bdi']  = [];
        $tags['img']  = array_merge(
            $tags['img'] ?? [],
            [
                'srcset'        => true,
                'sizes'         => true,
                'decoding'      => true,
                'fetchpriority' => true,
            ]
        );
        $tags['svg']  = [
            'class'           => true,
            'width'           => true,
            'height'          => true,
            'viewbox'         => true,
            'fill'            => true,
            'stroke'          => true,
            'stroke-width'    => true,
            'stroke-linecap'  => true,
            'stroke-linejoin' => true,
            'aria-hidden'     => true,
            'focusable'       => true,
            'xmlns'           => true,
        ];
        $tags['path'] = [
            'd'      => true,
            'fill'   => true,
            'stroke' => true,
        ];

        return $tags;
    }
}
