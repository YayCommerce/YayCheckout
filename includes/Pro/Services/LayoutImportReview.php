<?php
namespace YayCheckout\Pro\Services;

use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * Judges an export envelope before anything is written: the layout inside it, or why it cannot be imported.
 *
 * Pure: a function of the envelope. Other blocks, including YayCheckout blocks this version lacks,
 * pass through; WordPress renders an unregistered block as nothing and the editor flags it.
 */
class LayoutImportReview {

    /** Status rules_from_file() reports; the wire values of the import reply's `rules_import.status`. */
    public const RULES_IMPORTED = 'imported';
    public const RULES_DROPPED  = 'dropped';
    public const RULES_NONE     = 'none';

    /**
     * @return array { title, content, rules, design } rules as the file holds them, unchecked (see rules_from_file());
     *               design projected through LayoutDesign::from_meta().
     * @throws \InvalidArgumentException When the file is not an export or has no Checkout block.
     */
    public static function from_envelope( array $envelope ): array {
        $layout = self::layout( $envelope );

        // First of LayoutMarkupReader::publish_error()'s rules; the rest wait for publish, as for any draft.
        if ( ! LayoutMarkupReader::has_checkout( $layout['content'] ) ) {
            throw new \InvalidArgumentException( __( 'The file does not contain a checkout layout.', 'yaycheckout' ) );
        }

        return $layout;
    }

    /**
     * The file's assignment rules, if they can be kept. Rules this version cannot read (a type from a newer
     * plugin, a hand-edited file) are dropped whole, not per condition: dropping one condition of a group
     * would widen it, and the layout would then serve shoppers the file never meant it to.
     *
     * @param  mixed $rules The layout's `rules` from the file; null when the file has none.
     * @return array{rules: array, status: string} status: a RULES_* constant; RULES_NONE when the file has none.
     */
    public static function rules_from_file( $rules ): array {
        if ( null === $rules || [] === $rules ) {
            return [
                'rules'  => [],
                'status' => self::RULES_NONE,
            ];
        }

        try {
            return [
                'rules'  => Layout::sanitize_rules( $rules ),
                'status' => self::RULES_IMPORTED,
            ];
        } catch ( \InvalidArgumentException $e ) {
            return [
                'rules'  => [],
                'status' => self::RULES_DROPPED,
            ];
        }
    }

    /**
     * The design is projected, never refused: a bad value only changes how the layout looks, so a missing
     * or malformed section imports as empty and an invalid token reads as its empty default. A file from before
     * layout designs has none.
     *
     * @return array { title, content, rules, design }
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

        return [
            'title'   => $layout['title'],
            'content' => $layout['content'],
            'rules'   => $layout['rules'] ?? null,
            'design'  => LayoutDesign::from_meta( $layout['design'] ?? null ),
        ];
    }
}
