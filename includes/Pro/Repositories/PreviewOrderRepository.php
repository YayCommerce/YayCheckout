<?php
namespace YayCheckout\Pro\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * The one sample order the thank-you and pay-order previews show. A `checkout-draft` order, which WooCommerce leaves out
 * of the Orders list and reports, sends no email for and deletes a day after it was last changed; a
 * missing or deleted order is made again on the next preview. Made once and reused, so the hooks that
 * fire when an order is created fire once.
 */
class PreviewOrderRepository {

    public const OPTION_ORDER_ID = 'yaycheckout_preview_order_id';

    /** Marks the order as ours, so a reused id that now names a shopper's order is never shown. */
    public const META_FLAG = '_yaycheckout_preview_order';

    private const SAMPLE_PRODUCTS = 2;

    public function get_or_create(): \WC_Order {
        $order = $this->find();

        return null !== $order ? $order : $this->create();
    }

    /** The sample order, or null when none was made or it is gone. */
    public function find(): ?\WC_Order {
        $id = (int) get_option( self::OPTION_ORDER_ID, 0 );

        if ( $id <= 0 ) {
            return null;
        }

        $order = wc_get_order( $id );

        return $order instanceof \WC_Order && '' !== (string) $order->get_meta( self::META_FLAG ) ? $order : null;
    }

    /** True when $order is the sample order: a preview link shows no other. */
    public function is_sample( \WC_Order $order ): bool {
        $sample = $this->find();

        return null !== $sample && $sample->get_id() === $order->get_id();
    }

    private function create(): \WC_Order {
        $order = new \WC_Order();
        $order->set_status( 'checkout-draft' );
        $order->set_created_via( 'yaycheckout-preview' );
        $order->set_currency( get_woocommerce_currency() );

        foreach ( $this->sample_products() as $product ) {
            $order->add_product( $product, 1 );
        }

        // The addresses packages/blocks/src/pro/order-customer-details/edit.tsx shows in the editor; change both together.
        $name = [
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'company'    => 'YayCommerce',
            'phone'      => '(910) 529-1147',
        ];
        $order->set_address(
            $name + [
                'address_1' => '7400 Edwards Rd',
                'address_2' => 'Edwards Rd',
                'email'     => 'john.doe@example.com',
            ],
            'billing'
        );
        $order->set_address(
            $name + [
                'address_1' => '755 E North Grove Rd',
                // One line, as the editor shows it: without a country WooCommerce prints city and state apart.
                'city'      => 'Mayville, Michigan',
            ],
            'shipping'
        );

        // WooCommerce prints the shipping address only for an order with a shipping line.
        $shipping = new \WC_Order_Item_Shipping();
        $shipping->set_method_title( __( 'Free shipping', 'yaycheckout' ) );
        $shipping->set_method_id( 'free_shipping' );
        $shipping->set_total( 0 );
        $order->add_item( $shipping );

        // Enabled, not get_available_payment_gateways(): gateways' is_available() reads the cart, which a REST request has none of.
        foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
            if ( $gateway instanceof \WC_Payment_Gateway && 'yes' === $gateway->enabled ) {
                $order->set_payment_method( $gateway );
                break;
            }
        }

        $order->update_meta_data( self::META_FLAG, '1' );
        $order->calculate_totals();
        $order->save();

        update_option( self::OPTION_ORDER_ID, $order->get_id(), false );

        return $order;
    }

    /** @return \WC_Product[] The newest purchasable simple products in stock. */
    private function sample_products(): array {
        $products = wc_get_products(
            [
                'status'       => 'publish',
                'stock_status' => 'instock',
                'type'         => 'simple',
                'limit'        => self::SAMPLE_PRODUCTS,
                'orderby'      => 'date',
                'order'        => 'DESC',
            ]
        );

        return array_values(
            array_filter(
                $products,
                static function ( $product ) {
                    return $product instanceof \WC_Product && $product->is_purchasable();
                }
            )
        );
    }
}
