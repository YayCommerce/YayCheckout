<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Design;
use YayCheckout\Repositories\DesignRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the applied primary colour for checkout blocks.
 *
 * Checkout blocks read --yaycheckout-primary. The value comes from Design;
 * this file places it on the checkout wrapper so the layout editor and the
 * checkout page share it, including a Step Button or Back Button outside Steps.
 */
class CheckoutColour {

    use SingletonTrait;

    /** After block styles are enqueued on enqueue_block_assets. */
    private const PRIORITY = 20;

    protected function __construct() {
        add_action( 'enqueue_block_assets', [ $this, 'print_primary' ], self::PRIORITY );
    }

    public function print_primary(): void {
        $stored  = ( new DesignRepository() )->get()->tokens['colours']['primary'] ?? '';
        $primary = sanitize_hex_color( is_string( $stored ) ? $stored : '' );

        if ( ! is_string( $primary ) ) {
            $primary = sanitize_hex_color( (string) Design::schema()['colours']['primary']['default'] );
        }

        if ( ! is_string( $primary ) ) {
            return;
        }

        $css = '.yaycheckout-checkout{--yaycheckout-primary:' . $primary . ';}';

        foreach ( [ 'yaycheckout/steps', 'yaycheckout/step-button', 'yaycheckout/back-button' ] as $block ) {
            wp_add_inline_style( generate_block_asset_handle( $block, 'style' ), $css );
        }
    }
}
