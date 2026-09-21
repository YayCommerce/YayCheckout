<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Services\LayoutBlockIndex;
use YayCheckout\Services\ExtraFieldPostedCollector;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Persists published extra fields onto the WooCommerce order they were posted with.
 *
 * Reads the active layout from storage, not the storefront renderer, so this
 * class can ship with the extra-field block before CheckoutPage exists.
 */
class CheckoutExtraFields {

    use SingletonTrait;

    public const META_SNAPSHOT = '_yaycheckout_extra_fields';

    private LayoutRepository $layouts;

    protected function __construct() {
        $this->layouts = new LayoutRepository();

        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ], 10, 2 );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'persist_saved' ], 10, 2 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'print_admin' ] );
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'print_customer' ] );
    }

    /**
     * @param array     $data
     * @param \WP_Error $errors
     */
    public function validate( $data, $errors ) {
        unset( $data );

        if ( ! $errors instanceof \WP_Error ) {
            return;
        }

        $fields = $this->fields();

        if ( [] === $fields ) {
            return;
        }

        $result = ExtraFieldPostedCollector::collect( $fields, $this->posted() );

        foreach ( $result['errors'] as $error ) {
            $key   = isset( $error['key'] ) ? (string) $error['key'] : '';
            $code  = isset( $error['code'] ) ? (string) $error['code'] : 'invalid';
            $label = isset( $fields[ $key ]['label'] ) && is_string( $fields[ $key ]['label'] ) && '' !== $fields[ $key ]['label']
                ? $fields[ $key ]['label']
                : $key;

            if ( 'required' === $code ) {
                $message = sprintf(
                    /* translators: %s: extra field label. */
                    __( '%s is a required field.', 'yaycheckout' ),
                    $label
                );
            } else {
                $message = sprintf(
                    /* translators: %s: extra field label. */
                    __( '%s is not a valid selection.', 'yaycheckout' ),
                    $label
                );
            }

            $errors->add( 'yaycheckout_extra_' . $key, $message );
        }
    }

    /**
     * @param \WC_Order $order
     * @param array     $data
     */
    public function persist( $order, $data ) {
        unset( $data );

        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $fields = $this->fields();

        if ( [] === $fields ) {
            return;
        }

        $result = ExtraFieldPostedCollector::collect( $fields, $this->posted() );

        if ( [] !== $result['errors'] || [] === $result['rows'] ) {
            return;
        }

        foreach ( $result['rows'] as $row ) {
            $order->update_meta_data( $row['key'], $row['value'] );
        }

        $order->update_meta_data( self::META_SNAPSHOT, $result['rows'] );
    }

    /**
     * Woo's documented hook for custom order meta, after the order exists in the DB.
     *
     * @param int   $order_id
     * @param array $data
     */
    public function persist_saved( $order_id, $data ) {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $this->persist( $order, $data );
        $order->save_meta_data();
    }

    /**
     * @param \WC_Order $order
     */
    public function print_admin( $order ) {
        $order = $this->as_order( $order );

        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $this->print_rows( $order, true );
    }

    /**
     * @param \WC_Order $order
     */
    public function print_customer( $order ) {
        $order = $this->as_order( $order );

        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $this->print_rows( $order, false );
    }

    /**
     * @param mixed $order
     * @return \WC_Order|null
     */
    private function as_order( $order ): ?\WC_Order {
        if ( $order instanceof \WC_Order ) {
            return $order;
        }

        if ( function_exists( 'wc_get_order' ) && ( is_numeric( $order ) || $order instanceof \WP_Post ) ) {
            $found = wc_get_order( $order );

            return $found instanceof \WC_Order ? $found : null;
        }

        return null;
    }

    /**
     * @param \WC_Order $order
     * @param bool      $admin
     */
    private function print_rows( \WC_Order $order, bool $admin ): void {
        $rows = $order->get_meta( self::META_SNAPSHOT );

        if ( ! is_array( $rows ) || [] === $rows ) {
            return;
        }

        echo '<div class="yaycheckout-order-extra-fields">';

        if ( $admin ) {
            echo '<h3>' . esc_html__( 'Extra fields', 'yaycheckout' ) . '</h3>';
        }

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $label   = isset( $row['label'] ) ? (string) $row['label'] : '';
            $display = isset( $row['display'] ) ? (string) $row['display'] : '';

            if ( '' === $display && isset( $row['value'] ) ) {
                $display = is_array( $row['value'] ) ? implode( ', ', array_map( 'strval', $row['value'] ) ) : (string) $row['value'];
            }

            if ( '' === $label && '' === $display ) {
                continue;
            }

            echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $display ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * @return array
     */
    private function fields(): array {
        $layout = $this->layouts->resolve_for_request();

        if ( ! $layout instanceof Layout || ! $layout->is_published() || '' === trim( $layout->content ) ) {
            return [];
        }

        try {
            $index = LayoutBlockIndex::from_markup( $layout->content );
        } catch ( \InvalidArgumentException $ex ) {
            return [];
        }

        $fields = $index['extra_fields'] ?? [];

        return is_array( $fields ) ? $fields : [];
    }

    /**
     * @return array
     */
    private function posted(): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Woo verifies the checkout nonce before these hooks.
        if ( ! isset( $_POST ) || ! is_array( $_POST ) ) {
            return [];
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Keys are limited to the extra_fields map; ExtraFieldPostedCollector sanitizes values.
        return wp_unslash( $_POST );
    }
}
