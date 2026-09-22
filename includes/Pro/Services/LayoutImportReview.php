<?php
namespace YayCheckout\Pro\Services;

use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Models\Layout;
use YayCheckout\Services\LayoutBlockIndex;

defined( 'ABSPATH' ) || exit;

/**
 * Judges an export envelope before anything is written: what it contains, or why it cannot be imported.
 *
 * Pure: a function of the envelope. The same checks run for the preview and again
 * for the write, so the SPA can never import what it was not shown.
 */
class LayoutImportReview {

    /**
     * @param  array $envelope Decoded export file.
     * @return array { title, blocks }
     * @throws \InvalidArgumentException When the file is not an export, has no checkout root, or uses a block this site lacks.
     */
    public static function from_envelope( array $envelope ): array {
        $layout = self::layout( $envelope );
        $blocks = LayoutBlockIndex::real_blocks( parse_blocks( $layout['content'] ) );

        // Same rule the renderer enforces in LayoutBlockIndex: a layout is a checkout block, nothing else.
        if ( 'yaycheckout/checkout' !== ( $blocks[0]['blockName'] ?? null ) ) {
            throw new \InvalidArgumentException( __( 'The file does not contain a checkout layout.', 'yaycheckout' ) );
        }

        return [
            'title'  => $layout['title'],
            'blocks' => self::count_known( $blocks ),
        ];
    }

    /**
     * The one layout inside a valid envelope.
     *
     * @return array { title, content }
     * @throws \InvalidArgumentException When the envelope is not a YayCheckout export.
     */
    public static function layout( array $envelope ): array {
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
     * Counts every block in the tree, refusing the first one this site has not registered.
     *
     * @throws \InvalidArgumentException
     */
    private static function count_known( array $blocks ): int {
        $count = 0;

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

            $count += 1 + self::count_known( LayoutBlockIndex::real_blocks( $block['innerBlocks'] ?? [] ) );
        }

        return $count;
    }
}
