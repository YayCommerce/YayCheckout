<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * The served layout's steps: the Step children of its first Steps block. A layout holds one Checkout,
 * so that is also the first Steps block inside Checkout. Steps Progress and Go to Step (Pro) read it,
 * and so does BlockVisibility's step rule, which is why it stays in core.
 */
class LayoutSteps {

    private const STEPS = 'yaycheckout/steps';
    private const STEP  = 'yaycheckout/step';

    /** @var string[]|null Memoised layout_titles(); null until first read. */
    private static $titles = null;

    /**
     * Label stored on a step, or "Step" when the merchant left it empty.
     */
    public static function step_title( string $raw ): string {
        return '' !== $raw ? $raw : __( 'Step', 'yaycheckout' );
    }

    /**
     * A Step block's title as HTML safe to echo, with the display-text toolbar's formatting and live values,
     * or "Step" while it holds no text. The step's header and Steps Progress print it alike.
     *
     * @param array $attrs The Step block's attributes.
     */
    public static function step_title_html( array $attrs ): string {
        return LayoutMarkupReader::rich_text( $attrs, 'title', self::step_title( '' ) );
    }

    /**
     * Titles of the rendered layout's first Steps block, as HTML safe to echo; empty without a layout or a
     * Steps block, and in Free, where Steps isn't registered and a downgraded layout prints its steps' fields
     * as one page.
     *
     * @return string[]
     */
    public static function layout_titles(): array {
        if ( null === self::$titles ) {
            $layout       = CheckoutPage::get_instance()->layout();
            $has_steps    = null !== $layout && \WP_Block_Type_Registry::get_instance()->is_registered( self::STEPS );
            self::$titles = $has_steps ? self::titles_in( $layout->content ) : [];
        }

        return self::$titles;
    }

    /**
     * Titles of the first Steps block's Step children, in order, as step_title_html() prints them; empty without one.
     *
     * @return string[]
     */
    private static function titles_in( string $markup ): array {
        $block  = LayoutMarkupReader::first_block( $markup, self::STEPS );
        $titles = [];

        foreach ( null !== $block ? $block['innerBlocks'] : [] as $child ) {
            if ( self::STEP === ( $child['blockName'] ?? '' ) ) {
                $titles[] = self::step_title_html( is_array( $child['attrs'] ?? null ) ? $child['attrs'] : [] );
            }
        }

        return $titles;
    }
}
