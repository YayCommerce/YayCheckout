<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\ExtraFieldSubmission;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Validates the served layout's extra fields at checkout, stores them on the
 * order, and prints them on the admin and customer order screens. Fields with
 * Remember value on keep the shopper's entries in the WooCommerce session until
 * the order is created, so a reload prints them again.
 *
 * Reads the layout CheckoutPage serves, so a preview checkout validates and
 * saves the previewed layout's fields.
 */
class CheckoutExtraFields {

    use SingletonTrait;

    public const META_SNAPSHOT = '_yaycheckout_extra_fields';

    /** WooCommerce session key: Key => cleaned value of each remembered field. */
    public const SESSION_VALUES = 'yaycheckout_extra_field_values';

    protected function __construct() {
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate' ], 10, 2 );
        // Fires before WooCommerce saves the new order, so meta set here is saved with it.
        add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ] );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'print_admin' ] );
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'print_customer' ] );
        add_action( 'wc_ajax_yaycheckout_remember_extra_fields', [ $this, 'remember' ] );
    }

    /**
     * The remembered value of an extra field for this shopper, or null.
     *
     * @return string|string[]|null
     */
    public static function remembered_value( string $key ) {
        if ( ! function_exists( 'WC' ) || null === WC()->session ) {
            return null;
        }

        $values = WC()->session->get( self::SESSION_VALUES );

        return is_array( $values ) && isset( $values[ $key ] ) ? $values[ $key ] : null;
    }

    /**
     * Replaces the session's remembered values with the posted form's. Posted by
     * the Extra field view script on change; WooCommerce's update-order-review
     * nonce guards it, since it writes the same shopper's session.
     */
    public function remember(): void {
        if ( ! check_ajax_referer( 'update-order-review', 'security', false ) || null === WC()->session ) {
            wp_send_json_error( null, 403 );
        }

        $fields = array_filter( $this->fields(), static fn( $field ) => $field['remember'] );
        $values = [];

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; only remembered Keys are read and ExtraFieldSubmission sanitizes them.
        foreach ( ExtraFieldSubmission::collect( $fields, wp_unslash( $_POST ) )['rows'] as $row ) {
            $values[ $row['key'] ] = $row['value'];
        }

        WC()->session->set( self::SESSION_VALUES, [] === $values ? null : $values );
        wp_send_json_success();
    }

    public function validate( $data, \WP_Error $errors ): void {
        foreach ( $this->submission()['errors'] as $key => $message ) {
            $errors->add( 'yaycheckout_extra_' . $key, $message );
        }
    }

    /**
     * One meta per field plus a snapshot of the rows, so order screens keep the
     * label and display text after the layout changes. WooCommerce reuses a
     * pending or failed order on retry, so the previous attempt's values are
     * removed first; otherwise a field cleared on retry would keep its old value.
     */
    public function persist( \WC_Order $order ): void {
        $submission = $this->submission();

        if ( [] !== $submission['errors'] ) {
            return;
        }

        // The order now holds them; the next checkout starts empty.
        if ( null !== WC()->session ) {
            WC()->session->set( self::SESSION_VALUES, null );
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

    /** @return array{rows: array, errors: array<string, string>} */
    private function submission(): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Woo verifies the checkout nonce before these hooks; only the layout's Keys are read and ExtraFieldSubmission sanitizes them.
        return ExtraFieldSubmission::collect( $this->fields(), wp_unslash( $_POST ) );
    }

    /** The served layout's extra fields, or none when the renderer stands down. */
    private function fields(): array {
        $layout = CheckoutPage::get_instance()->layout();

        return null === $layout ? [] : LayoutMarkupReader::extra_fields( $layout->content );
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
