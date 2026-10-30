<?php
namespace YayCheckout\Pro\Renderer;

use YayCheckout\Renderer\CheckoutFragments;

defined( 'ABSPATH' ) || exit;

/**
 * Pro: the Order Details block's display options on top of WooCommerce's own order/order-details.php.
 * Each option is a WooCommerce filter added only while the block prints the table, plus classes and CSS
 * vars on the block wrapper that the block's style.css reads. With every option at its block.json default
 * nothing is added, so the table prints exactly as WooCommerce prints it.
 */
class OrderDetailsTable {

    const ITEM_CLASS  = 'yaycheckout-order-details__item';
    const TOTAL_CLASS = 'yaycheckout-order-details__total';

    /**
     * Classes and inline CSS vars for get_block_wrapper_attributes(). orderDetailsWrapperProps() in
     * packages/blocks/src/pro/order-details/edit.tsx mirrors it.
     *
     * @param array $attributes Block attributes.
     * @return array{class: string, style: string} Both empty at the defaults.
     */
    public static function wrapper_attributes( array $attributes ): array {
        $options = self::options( $attributes );
        $classes = [];
        $decls   = [];

        if ( $options['show_thumbnails'] ) {
            $decls = CheckoutFragments::thumbnail_style_vars( $attributes );
        }

        if ( 'minimal' === $options['table_style'] ) {
            $classes[] = 'yaycheckout-order-details--minimal';

            if ( $options['show_divider'] ) {
                $classes[] = 'yaycheckout-order-details--divider';
                if ( '' !== $options['divider_color'] ) {
                    $decls[] = '--yaycheckout-order-details-divider:' . $options['divider_color'];
                }
            }
        }

        if ( '' !== $options['total_color'] ) {
            $classes[] = 'yaycheckout-order-details--total-color';
            $decls[]   = '--yaycheckout-order-details-total-color:' . $options['total_color'];
        }

        return [
            'class' => implode( ' ', $classes ),
            'style' => implode( ';', $decls ),
        ];
    }

    /**
     * Runs $callback, which prints the WooCommerce table, with the filters the block's options need. The
     * filters come off afterwards, so order emails and My Account keep WooCommerce's table.
     *
     * @param array    $attributes Block attributes.
     * @param callable $callback   Printer that echoes woocommerce_order_details_table().
     */
    public static function with_display_filters( array $attributes, callable $callback ): void {
        $options    = self::options( $attributes );
        $mark_total = '' !== $options['total_color'];
        $total_last = $options['total_last'];

        $item_name = $options['show_thumbnails'] ? [ self::class, 'item_name_with_thumbnail' ] : null;
        $totals    = $mark_total || $total_last
            ? static function ( $rows ) use ( $total_last, $mark_total ) {
                return is_array( $rows ) ? self::order_totals( $rows, $total_last, $mark_total ) : $rows;
            }
            : null;

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's filters.
        if ( null !== $item_name ) {
            add_filter( 'woocommerce_order_item_name', $item_name, 10, 2 );
        }
        if ( null !== $totals ) {
            add_filter( 'woocommerce_get_order_item_totals', $totals, PHP_INT_MAX );
        }

        try {
            $callback();
        } finally {
            if ( null !== $item_name ) {
                remove_filter( 'woocommerce_order_item_name', $item_name, 10 );
            }
            if ( null !== $totals ) {
                remove_filter( 'woocommerce_get_order_item_totals', $totals, PHP_INT_MAX );
            }
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    }

    /**
     * The line item's name (WooCommerce's linked or plain name HTML) with its product thumbnail before it.
     * A line item whose product is gone shows WooCommerce's placeholder image.
     *
     * @param string|mixed $name Name HTML from order/order-details-item.php.
     * @param mixed        $item The order item.
     * @return string|mixed
     */
    public static function item_name_with_thumbnail( $name, $item ) {
        if ( ! is_string( $name ) || ! $item instanceof \WC_Order_Item ) {
            return $name;
        }

        $product = $item instanceof \WC_Order_Item_Product ? $item->get_product() : null;
        $image   = $product instanceof \WC_Product ? $product->get_image( 'woocommerce_thumbnail' ) : wc_placeholder_img( 'woocommerce_thumbnail' );

        return '<span class="' . self::ITEM_CLASS . '">'
            . '<span class="yaycheckout-order-details__thumbnail">' . $image . '</span>'
            . '<span class="yaycheckout-order-details__name">' . $name . '</span>'
            . '</span>';
    }

    /**
     * WooCommerce's totals rows with the order total marked for its colour and, when $total_last, moved after
     * every other row (payment method and refunds included). Rows without an order_total are unchanged.
     *
     * @param array<string, array{label: string, value: string}> $rows From WC_Order::get_order_item_totals().
     * @return array<string, array{label: string, value: string}>
     */
    public static function order_totals( array $rows, bool $total_last, bool $mark_total ): array {
        if ( ! isset( $rows['order_total'] ) || ! is_array( $rows['order_total'] ) ) {
            return $rows;
        }

        $total = $rows['order_total'];

        if ( $mark_total && isset( $total['value'] ) ) {
            $total['value'] = '<span class="' . self::TOTAL_CLASS . '">' . $total['value'] . '</span>';
        }

        if ( $total_last ) {
            unset( $rows['order_total'] );
        }

        $rows['order_total'] = $total;

        return $rows;
    }

    /**
     * The display options with block.json's defaults; a stored layout leaves an untouched attribute out.
     *
     * @param array $attributes Block attributes.
     * @return array{show_thumbnails: bool, table_style: string, show_divider: bool, divider_color: string, total_last: bool, total_color: string}
     */
    private static function options( array $attributes ): array {
        $bool = static function ( string $name ) use ( $attributes ): bool {
            return isset( $attributes[ $name ] ) && true === $attributes[ $name ];
        };

        return [
            'show_thumbnails' => $bool( 'showThumbnails' ),
            'table_style'     => isset( $attributes['tableStyle'] ) && 'minimal' === $attributes['tableStyle'] ? 'minimal' : 'boxed',
            'show_divider'    => $bool( 'showDivider' ),
            'divider_color'   => CheckoutFragments::css_color( $attributes['dividerColor'] ?? null ),
            'total_last'      => $bool( 'totalLast' ),
            'total_color'     => CheckoutFragments::css_color( $attributes['totalColor'] ?? null ),
        ];
    }
}
