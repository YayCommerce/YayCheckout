<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Services\CheckoutFieldRequirements as Requirements;
use YayCheckout\Services\LayoutBlockIndex;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Makes WooCommerce required flags follow the layout, then undoes address-i18n.js
 * when it restores locale defaults on country change.
 *
 * Stands down when CheckoutPage has no layout, so stock WooCommerce checkout
 * keeps its own required set. See docs/system-architecture.md, Validation.
 */
class CheckoutFieldRequirements {

    use SingletonTrait;

    /** After locale and third-party woocommerce_checkout_fields callbacks. */
    private const FILTER_PRIORITY = 9999;

    /** After CheckoutPage enqueues wc-address-i18n. */
    private const SCRIPTS_PRIORITY = 21;

    private const SCRIPT_HANDLE = 'yaycheckout-field-requirements';

    /** @var array|null|false false until first resolved. */
    private $index = false;

    protected function __construct() {
        add_filter( 'woocommerce_checkout_fields', [ $this, 'filter_fields' ], self::FILTER_PRIORITY );
        add_filter( 'woocommerce_form_field', [ $this, 'filter_form_field' ], self::FILTER_PRIORITY, 3 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ], self::SCRIPTS_PRIORITY );
    }

    /**
     * @param  mixed $fields
     * @return mixed
     */
    public function filter_fields( $fields ) {
        if ( ! is_array( $fields ) ) {
            return $fields;
        }

        $layout_fields = $this->layout_fields();

        if ( null === $layout_fields ) {
            return $fields;
        }

        return Requirements::apply( $fields, $layout_fields );
    }

    /**
     * @param  mixed $field
     * @param  mixed $key
     * @param  mixed $args
     * @return mixed
     */
    public function filter_form_field( $field, $key, $args ) {
        $layout_fields = $this->indicator_fields();

        if ( null === $layout_fields ) {
            return $field;
        }

        return Requirements::replace_form_field(
            $field,
            is_string( $key ) ? $key : '',
            is_array( $args ) ? $args : [],
            $layout_fields
        );
    }

    public function enqueue_script(): void {
        if ( ! wp_script_is( 'wc-address-i18n', 'enqueued' ) ) {
            return;
        }

        $layout_fields = $this->indicator_fields();

        if ( null === $layout_fields ) {
            return;
        }

        $json = wp_json_encode( Requirements::required_map( $layout_fields ) );

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
     * @return array|null Null when the renderer stands down.
     */
    private function layout_fields(): ?array {
        $index = $this->layout_index();

        if ( null === $index ) {
            return null;
        }

        $fields = $index['fields'] ?? [];

        return is_array( $fields ) ? $fields : [];
    }

    /**
     * Address fields plus extra fields, so form_field and the storefront script
     * share one lookup.
     *
     * @return array|null Null when the renderer stands down.
     */
    private function indicator_fields(): ?array {
        $index = $this->layout_index();

        if ( null === $index ) {
            return null;
        }

        $fields = isset( $index['fields'] ) && is_array( $index['fields'] ) ? $index['fields'] : [];
        $extra  = isset( $index['extra_fields'] ) && is_array( $index['extra_fields'] ) ? $index['extra_fields'] : [];

        return array_merge( $fields, $extra );
    }

    /**
     * @return array|null Null when the renderer stands down.
     */
    private function layout_index(): ?array {
        if ( false !== $this->index ) {
            return $this->index;
        }

        $layout = CheckoutPage::get_instance()->layout();

        if ( ! $layout instanceof Layout || '' === trim( $layout->content ) ) {
            $this->index = null;

            return null;
        }

        try {
            $this->index = LayoutBlockIndex::from_markup( $layout->content );
        } catch ( \InvalidArgumentException $ex ) {
            $this->index = null;
        }

        return $this->index;
    }
}
