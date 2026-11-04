<?php
namespace YayCheckout\Pro\Renderer;

use YayCheckout\Renderer\CheckoutFragments;

defined( 'ABSPATH' ) || exit;

/**
 * The Thank You Text block's markup: one of the SVG artworks in includes/Pro/templates/thank-you-text/,
 * optionally recoloured. Each artwork keeps its original colours in its fill and stroke attributes; a colour
 * the merchant sets becomes a variable that style.css applies to the slot's classes only while the wrapper
 * carries has-main-color or has-secondary-color, so an unset slot keeps the original.
 */
class ThankYouText {

    /**
     * Each artwork => the colour slots the merchant may change. Mirrors thank-you-text/block.json "variant" enum.
     * Multicolour artworks have none: their palette is the design.
     */
    private const VARIANTS = [
        '1a' => [ 'main' ],
        '1b' => [ 'main', 'secondary' ],
        '1c' => [ 'main', 'secondary' ],
        '1d' => [],
        '1e' => [ 'main', 'secondary' ],
        '1f' => [ 'main' ],
        '1g' => [ 'main' ],
        '1h' => [],
        '1i' => [ 'main' ],
        '1j' => [ 'main' ],
        '2a' => [ 'main', 'secondary' ],
        '2b' => [ 'main' ],
        '2c' => [ 'main' ],
        '2d' => [ 'main', 'secondary' ],
        '2e' => [ 'main', 'secondary' ],
        '2f' => [ 'main' ],
        '2g' => [ 'main', 'secondary' ],
        '2h' => [ 'main' ],
        '2i' => [ 'main', 'secondary' ],
        '2j' => [ 'main' ],
        '2k' => [ 'main' ],
        '2l' => [ 'main', 'secondary' ],
        '3a' => [],
        '3b' => [ 'main' ],
        '3c' => [],
        '3d' => [],
        '3e' => [ 'main' ],
        '3f' => [],
        '3g' => [],
        '3h' => [ 'main' ],
    ];

    public const DEFAULT_VARIANT = '1e';

    /** Slot => the design token it follows while Use theme colours is on. */
    private const THEME_COLORS = [
        'main'      => 'var(--yaycheckout-design-primary)',
        'secondary' => 'var(--yaycheckout-design-text)',
    ];

    /** Slot => its colour attribute. */
    private const COLOR_ATTRIBUTES = [
        'main'      => 'mainColor',
        'secondary' => 'secondaryColor',
    ];

    private const ALIGNMENTS = [ 'left', 'center', 'right' ];

    /** @var array<string, string> Artwork markup read this request, by id. */
    private static $svgs = [];

    /**
     * Every artwork, in the block's enum order, for the editor's picker and canvas.
     *
     * @return array<int, array{id: string, slots: string[], svg: string}>
     */
    public static function variants(): array {
        $variants = [];

        foreach ( self::VARIANTS as $id => $slots ) {
            $variants[] = [
                'id'    => (string) $id,
                'slots' => $slots,
                'svg'   => self::svg( (string) $id ),
            ];
        }

        return $variants;
    }

    /** The artwork's SVG, hidden from assistive tech since the wrapper carries the label. Empty when unreadable. */
    public static function svg( string $id ): string {
        if ( ! isset( self::VARIANTS[ $id ] ) ) {
            return '';
        }

        if ( ! isset( self::$svgs[ $id ] ) ) {
            $path = __DIR__ . '/../templates/thank-you-text/' . $id . '.svg';
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local plugin file.
            $svg = is_readable( $path ) ? (string) file_get_contents( $path ) : '';

            // The viewBox size becomes the natural size, so the artwork shows at its own width until Max width or its container narrows it.
            $size = preg_match( '/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $svg, $match ) ? ' width="' . $match[1] . '" height="' . $match[2] . '"' : '';

            self::$svgs[ $id ] = '' === $svg ? '' : (string) preg_replace( '/^<svg\b/', '<svg aria-hidden="true" focusable="false"' . $size, trim( $svg ), 1 );
        }

        return self::$svgs[ $id ];
    }

    /** Prints the editor's artworks before the block's editor script. */
    public static function editor_data(): void {
        $handle = generate_block_asset_handle( 'yaycheckout/thank-you-text', 'editorScript' );

        if ( wp_script_is( $handle, 'registered' ) ) {
            wp_add_inline_script( $handle, 'window.yaycheckoutThankYouText = ' . wp_json_encode( [ 'variants' => self::variants() ] ) . ';', 'before' );
        }
    }

    /**
     * The block's markup. An unknown variant prints the default; a colour applies only to a slot the artwork has.
     * Mirrors wrapperProps() in thank-you-text/edit.tsx.
     *
     * @param array $attributes Block attributes.
     */
    public static function markup( array $attributes ): string {
        $variant = $attributes['variant'] ?? '';
        $variant = is_string( $variant ) && isset( self::VARIANTS[ $variant ] ) ? $variant : self::DEFAULT_VARIANT;

        $alignment = $attributes['alignment'] ?? '';
        $alignment = in_array( $alignment, self::ALIGNMENTS, true ) ? $alignment : 'center';

        $classes = [ 'yaycheckout-thank-you-text', 'is-align-' . $alignment ];
        $style   = '';

        foreach ( self::VARIANTS[ $variant ] as $slot ) {
            $color = CheckoutFragments::css_color( $attributes[ self::COLOR_ATTRIBUTES[ $slot ] ] ?? null );

            if ( '' === $color && ! empty( $attributes['useThemeColors'] ) ) {
                $color = self::THEME_COLORS[ $slot ];
            }

            if ( '' !== $color ) {
                $classes[] = 'has-' . $slot . '-color';
                $style    .= '--yaycheckout-thank-you-text-' . $slot . ':' . $color . ';';
            }
        }

        $max_width = $attributes['maxWidth'] ?? null;

        if ( is_numeric( $max_width ) && $max_width > 0 ) {
            $style .= '--yaycheckout-thank-you-text-max-width:' . (float) $max_width . 'px;';
        }

        $args = [
            'class'      => implode( ' ', $classes ),
            'role'       => 'img',
            'aria-label' => __( 'Thank you', 'yaycheckout' ),
        ];

        if ( '' !== $style ) {
            $args['style'] = $style;
        }

        return '<div ' . get_block_wrapper_attributes( $args ) . '>' . self::svg( $variant ) . '</div>';
    }
}
