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

    /**
     * The storefront_html() tags plus the form controls a shortcode may print (a newsletter or login form),
     * for the shortcodes LayoutShortcodes runs in a layout's text blocks.
     */
    public static function shortcode_html(): array {
        static $tags = null;

        if ( null !== $tags ) {
            return $tags;
        }

        $control = [
            'id'          => true,
            'class'       => true,
            'name'        => true,
            'value'       => true,
            'disabled'    => true,
            'required'    => true,
            'placeholder' => true,
            'aria-label'  => true,
        ];

        $tags = array_merge(
            self::storefront_html(),
            _wp_add_global_attributes(
                [
                    'form'     => [
                        'id'      => true,
                        'class'   => true,
                        'action'  => true,
                        'method'  => true,
                        'enctype' => true,
                        'name'    => true,
                    ],
                    'input'    => array_merge(
                        $control,
                        [
                            'type'         => true,
                            'checked'      => true,
                            'min'          => true,
                            'max'          => true,
                            'step'         => true,
                            'size'         => true,
                            'maxlength'    => true,
                            'autocomplete' => true,
                        ]
                    ),
                    'select'   => array_merge( $control, [ 'multiple' => true ] ),
                    'option'   => [
                        'value'    => true,
                        'selected' => true,
                        'disabled' => true,
                    ],
                    'textarea' => array_merge(
                        $control,
                        [
                            'rows' => true,
                            'cols' => true,
                        ]
                    ),
                    'button'   => array_merge( $control, [ 'type' => true ] ),
                    'label'    => [
                        'for'   => true,
                        'class' => true,
                    ],
                ]
            )
        );

        return $tags;
    }
}
