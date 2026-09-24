<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\ExtraFieldSubmission;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Validates the served layout's extra fields at checkout, stores them on the
 * order, and prints them on the admin and customer order screens.
 *
 * Reads the layout CheckoutPage serves, so a preview checkout validates and
 * saves the previewed layout's fields.
 */
class CheckoutExtraFields {

    use SingletonTrait;

    public const META_SNAPSHOT = '_yaycheckout_extra_fields';

    protected function __construct() {
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate' ], 10, 2 );
        // Fires before WooCommerce saves the new order, so meta set here is saved with it.
        add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ] );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'print_admin' ] );
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'print_customer' ] );
    }

    /**
     * @param array     $data   WooCommerce's posted checkout data; unused.
     * @param \WP_Error $errors
     */
    public function validate( $data, \WP_Error $errors ): void {
        foreach ( $this->submission()['errors'] as $key => $message ) {
            $errors->add( 'yaycheckout_extra_' . $key, $message );
        }
    }

    /**
     * One meta per field plus a snapshot of the rows, so order screens keep the
     * label and display text after the layout changes.
     *
     * WooCommerce reuses a pending or failed order when the shopper retries
     * with the same cart, so the previous attempt's values are removed first;
     * otherwise a field cleared on retry would keep its old value.
     */
    public function persist( \WC_Order $order ): void {
        $submission = $this->submission();

        if ( [] !== $submission['errors'] ) {
            return;
        }

        $previous = $order->get_meta( self::META_SNAPSHOT );

        foreach ( is_array( $previous ) ? $previous : [] as $row ) {
            $order->delete_meta_data( (string) ( $row['key'] ?? '' ) );
        }

        $order->delete_meta_data( self::META_SNAPSHOT );

        if ( [] === $submission['rows'] ) {
            return;
        }

        foreach ( $submission['rows'] as $row ) {
            $order->update_meta_data( $row['key'], $row['value'] );
        }

        $order->update_meta_data( self::META_SNAPSHOT, $submission['rows'] );
    }

    public function print_admin( \WC_Order $order ): void {
        $this->print_rows( $order, true );
    }

    public function print_customer( \WC_Order $order ): void {
        $this->print_rows( $order, false );
    }

    /**
     * @return array{rows: array, errors: array<string, string>}
     */
    private function submission(): array {
        $layout = CheckoutPage::get_instance()->layout();
        $fields = null === $layout ? [] : LayoutMarkupReader::extra_fields( $layout->content );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Woo verifies the checkout nonce before these hooks; only Keys in $fields are read and ExtraFieldSubmission sanitizes them.
        return ExtraFieldSubmission::collect( $fields, wp_unslash( $_POST ) );
    }

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
            $label   = (string) ( $row['label'] ?? '' );
            $display = (string) ( $row['display'] ?? '' );

            if ( '' === $label && '' === $display ) {
                continue;
            }

            echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $display ) . '</p>';
        }

        echo '</div>';
    }
}
