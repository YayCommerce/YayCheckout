<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Design;
use YayCheckout\Repositories\DesignRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the applied primary colour for checkout blocks.
 *
 * The accordion stylesheet reads --yaycheckout-primary. The value comes from
 * Design; this file only places it on the block style so the layout editor
 * and the checkout page share it.
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

        wp_add_inline_style(
            'yaycheckout-accordion-style',
            '.yaycheckout-accordion{--yaycheckout-primary:' . $primary . ';}'
        );
    }
}
