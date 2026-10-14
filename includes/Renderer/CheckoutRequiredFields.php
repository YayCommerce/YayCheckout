<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Services\RequiredFieldOverlay;
use YayCheckout\Services\RequiredIndicator;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Makes WooCommerce's required flags and label suffixes follow the served layout,
 * including after address-i18n.js resets them on country change.
 *
 * Stands down when CheckoutPage has no layout, so stock WooCommerce checkout
 * keeps its own required set.
 */
class CheckoutRequiredFields {

    use SingletonTrait;

    /** After locale and third-party woocommerce_checkout_fields callbacks. */
    private const FILTER_PRIORITY = 9999;

    /** After CheckoutPage enqueues wc-address-i18n. */
    private const SCRIPTS_PRIORITY = 21;

    private const SCRIPT_HANDLE = 'yaycheckout-field-requirements';

    /** @var array{address: array, all: array, order_note: array|null}|null|false False until first read; null when standing down. */
    private $fields = false;

    protected function __construct() {
        // Not gated on the page: checkout POST validates at wp_loaded, before is_page() works, and would drop the layout's required flags.
        add_filter( 'woocommerce_checkout_fields', [ $this, 'filter_fields' ], self::FILTER_PRIORITY );
        add_filter( 'woocommerce_form_field', [ $this, 'filter_form_field' ], self::FILTER_PRIORITY, 3 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ], self::SCRIPTS_PRIORITY );
    }

    public function filter_fields( $fields ) {
        if ( ! is_array( $fields ) ) {
            return $fields;
        }

        $layout_fields = $this->fields();

        if ( null === $layout_fields ) {
            return $fields;
        }

        return RequiredFieldOverlay::apply_order_note(
            RequiredFieldOverlay::apply( $fields, $layout_fields['address'], CheckoutFieldUsage::locale_hidden_keys( $fields ) ),
            $layout_fields['order_note']
        );
    }

    public function filter_form_field( $field, $key, $args ) {
        // Account and registration forms use this hook. is_checkout() stays true on wc-ajax; is_page() does not.
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return $field;
        }

        $layout_fields = $this->fields();

        if ( null === $layout_fields ) {
            return $field;
        }

        return RequiredIndicator::swap_in(
            $field,
            is_string( $key ) ? $key : '',
            is_array( $args ) ? $args : [],
            $layout_fields['all']
        );
    }

    public function enqueue_script(): void {
        // Account address pages enqueue wc-address-i18n as well.
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

        if ( ! wp_script_is( 'wc-address-i18n', 'enqueued' ) ) {
            return;
        }

        $layout_fields = $this->fields();

        if ( null === $layout_fields ) {
            return;
        }

        $json = wp_json_encode( RequiredIndicator::script_payload( $layout_fields['all'] ) );

        if ( ! is_string( $json ) || '' === $json ) {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            YAYCHECKOUT_PLUGIN_URL . 'assets/js/checkout-field-requirements.js',
            [ 'jquery', 'wc-address-i18n' ],
            YAYCHECKOUT_VERSION,
            true
        );

        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'window.yayCheckoutRequired = ' . $json . ';',
            'before'
        );
    }

    /**
     * The served layout's address fields; those plus its extra fields and order note; and its order note.
     *
     * @return array{address: array, all: array, order_note: array|null}|null Null when no layout is served, so WooCommerce keeps its own rules.
     */
    private function fields(): ?array {
        if ( false !== $this->fields ) {
            return $this->fields;
        }

        $layout = CheckoutPage::get_instance()->layout();

        if ( ! $layout instanceof Layout || '' === trim( $layout->content ) ) {
            $this->fields = null;

            return null;
        }

        $address    = LayoutMarkupReader::fields( $layout->content );
        $order_note = LayoutMarkupReader::order_note( $layout->content );
        // Not array_merge(): an all-digit extra field Key is an integer key, which array_merge() renumbers.
        $all = array_replace( $address, LayoutMarkupReader::extra_fields( $layout->content ) );

        if ( null !== $order_note ) {
            $all[ LayoutMarkupReader::ORDER_NOTE_KEY ] = $order_note;
        }

        $this->fields = [
            'address'    => $address,
            'all'        => $all,
            'order_note' => $order_note,
        ];

        return $this->fields;
    }
}
