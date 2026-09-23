<?php
namespace YayCheckout\Pro\Services;

use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Models\Layout;
use YayCheckout\Services\LayoutBlockIndex;

defined( 'ABSPATH' ) || exit;

/**
 * Judges an export envelope before anything is written: the layout inside it, or why it cannot be imported.
 *
 * Pure: a function of the envelope. The SPA only reads the file, so these are the checks that
 * know which blocks this site registers.
 */
class LayoutImportReview {

    /**
     * @param  array $envelope Decoded export file.
     * @return array { title, content }
     * @throws \InvalidArgumentException When the file is not an export, has no checkout root, or uses a block this site lacks.
     */
    public static function from_envelope( array $envelope ): array {
        $layout = self::layout( $envelope );
        $blocks = LayoutBlockIndex::real_blocks( parse_blocks( $layout['content'] ) );

        // Same rule the renderer enforces in LayoutBlockIndex: a layout is a checkout block, nothing else.
        if ( 'yaycheckout/checkout' !== ( $blocks[0]['blockName'] ?? null ) ) {
            throw new \InvalidArgumentException( __( 'The file does not contain a checkout layout.', 'yaycheckout' ) );
        }

        self::assert_known( $blocks );

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

    /**
     * Refuses the first block in the tree this site has not registered.
     *
     * @throws \InvalidArgumentException
     */
    private static function assert_known( array $blocks ): void {
        foreach ( $blocks as $block ) {
            if ( ! in_array( $block['blockName'], BlockRegistry::block_names(), true ) ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        /* translators: %s: block name. */
                        __( 'The file uses a block this site does not have: %s.', 'yaycheckout' ),
                        $block['blockName']
                    )
                );
            }

            self::assert_known( LayoutBlockIndex::real_blocks( $block['innerBlocks'] ?? [] ) );
        }
    }
}
