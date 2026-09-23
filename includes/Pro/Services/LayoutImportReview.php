<?php
namespace YayCheckout\Pro\Services;

use YayCheckout\Models\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * Judges an export envelope before anything is written: the layout inside it, or why it cannot be imported.
 *
 * Pure: a function of the envelope. Other blocks, including YayCheckout blocks this version lacks,
 * pass through; WordPress renders an unregistered block as nothing and the editor flags it.
 */
class LayoutImportReview {

    /**
     * @param  array $envelope Decoded export file.
     * @return array { title, content }
     * @throws \InvalidArgumentException When the file is not an export or has no Checkout block.
     */
    public static function from_envelope( array $envelope ): array {
        $layout = self::layout( $envelope );

        // Same rule the renderer enforces in LayoutBlockIndex::from_markup().
        if ( ! has_block( 'yaycheckout/checkout', $layout['content'] ) ) {
            throw new \InvalidArgumentException( __( 'The file does not contain a checkout layout.', 'yaycheckout' ) );
        }

        return $layout;
    }

    /**
     * The one layout inside a valid envelope.
     *
     * @return array { title, content }
     * @throws \InvalidArgumentException When the envelope is not a YayCheckout export.
     */
    private static function layout( array $envelope ): array {
        if ( Layout::EXPORT_FORMAT !== ( $envelope['format'] ?? null ) ) {
            throw new \InvalidArgumentException( __( 'This file is not a YayCheckout layout export.', 'yaycheckout' ) );
        }

        $layouts = is_array( $envelope['layouts'] ?? null ) ? $envelope['layouts'] : [];
        $layout  = 1 === count( $layouts ) ? reset( $layouts ) : null;

        if ( ! is_array( $layout ) || ! is_string( $layout['title'] ?? null ) || ! is_string( $layout['content'] ?? null ) ) {
            throw new \InvalidArgumentException( __( 'The file must contain exactly one layout.', 'yaycheckout' ) );
        }

        return $layout;
    }
}
