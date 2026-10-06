<?php
namespace YayCheckout\Renderer;

use YayCheckout\Repositories\DesignRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the applied primary colour for checkout blocks.
 *
 * Checkout blocks read --yaycheckout-primary. The value comes from Design;
 * this file places it on the checkout wrapper so the layout editor and the
 * checkout page share it, including Progress, a Next Step Button, a Previous Step Button, or Place Order
 * outside Steps. Each of those blocks' roots gets it too, for the inserter preview,
 * which renders the block alone without the wrapper.
 */
class CheckoutColour {

    use SingletonTrait;

    /** After block styles are enqueued on enqueue_block_assets. */
    private const PRIORITY = 20;

    protected function __construct() {
        add_action( 'enqueue_block_assets', [ $this, 'print_primary' ], self::PRIORITY );
    }

    public function print_primary(): void {
        // Design::from_option() already narrows the token to a valid hex colour or the schema default.
        $primary = ( new DesignRepository() )->get()->tokens['colours']['primary'];

        $css = '.yaycheckout-checkout,.wp-block-yaycheckout-progress,.wp-block-yaycheckout-steps,.wp-block-yaycheckout-step-button,.wp-block-yaycheckout-back-button,.wp-block-yaycheckout-place-order{--yaycheckout-primary:' . $primary . ';}';

        foreach ( [ 'yaycheckout/progress', 'yaycheckout/steps', 'yaycheckout/step-button', 'yaycheckout/back-button', 'yaycheckout/place-order' ] as $block ) {
            wp_add_inline_style( generate_block_asset_handle( $block, 'style' ), $css );
        }
    }
}
