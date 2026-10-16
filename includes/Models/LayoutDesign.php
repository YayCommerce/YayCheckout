<?php
namespace YayCheckout\Models;

defined( 'ABSPATH' ) || exit;

/**
 * A layout's whole design, and the only design model: every section's tokens, plus custom CSS printed after the
 * site-wide custom CSS. An empty token ('' or null) prints nothing, so the theme shows through.
 * Stored as Layout::META_DESIGN; to_meta() is also its wire and export shape.
 */
final class LayoutDesign {

    public const CUSTOM_CSS_MAX_BYTES = 50000;

    /** Six-digit #rrggbb, no alpha. Unanchored by delimiters so a JSON Schema `pattern` can use it too. */
    public const HEX_COLOR_PATTERN = '^#[0-9A-Fa-f]{6}$';

    /**
     * A gradient a `fill` token may hold instead of a colour. It is printed unescaped inside a declaration,
     * so quotes, backslashes, `*`, `;`, braces and angle brackets are refused: none can end the declaration,
     * open a comment or close the style element.
     */
    public const GRADIENT_PATTERN = '^(repeating-)?(linear|radial|conic)-gradient\\([^"\'\\\\*;{}<>]*\\)$';

    /**
     * A theme colour preset, by the custom property WordPress prints for it, so the token follows the theme's palette.
     * Not one of the design's own presets (design-presets.css): each is read from a colour token, so a token naming
     * one would read itself.
     */
    public const PRESET_COLOR_PATTERN = '^var\\(--wp--preset--color--(?!' . self::DESIGN_PRESET_PREFIX . ')[a-z0-9-]+\\)$';

    /** The slug prefix of the palette presets that offer the colour tokens to block colour pickers (addDesignPalette() in layout-design.tsx). */
    public const DESIGN_PRESET_PREFIX = 'yaycheckout-';

    /** A theme gradient preset, the same way. */
    public const PRESET_GRADIENT_PATTERN = '^var\\(--wp--preset--gradient--[a-z0-9-]+\\)$';

    /** Longest gradient a `fill` token accepts; a picked or theme gradient is far shorter. */
    public const GRADIENT_MAX_LENGTH = 1000;

    /**
     * Fonts the merchant can pick: token slug => CSS font-family, '' for the theme's font. A FONT_STACKS key
     * maps to its label instead. The other fonts are in core's Google Fonts collection, under the same slug:
     * picking one installs it into the Font Library (Repositories\FontRepository) unless the theme loads it already.
     */
    public const FONT_FAMILIES = [
        ''                 => '',
        'system'           => 'System font',
        'inter'            => 'Inter',
        'roboto'           => 'Roboto',
        'open-sans'        => 'Open Sans',
        'lato'             => 'Lato',
        'montserrat'       => 'Montserrat',
        'poppins'          => 'Poppins',
        'nunito'           => 'Nunito',
        'source-sans-3'    => 'Source Sans 3',
        'manrope'          => 'Manrope',
        'merriweather'     => 'Merriweather',
        'playfair-display' => 'Playfair Display',
        'roboto-slab'      => 'Roboto Slab',
    ];

    /**
     * The FONT_FAMILIES keys the shopper's device already has: each prints as this stack, with no fallback added,
     * and has nothing to install or load.
     */
    public const FONT_STACKS = [
        'system' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
    ];

    /** The FONT_FAMILIES keys whose fallback is a serif stack; the rest fall back to sans-serif. */
    public const SERIF_FONTS = [ 'merriweather', 'playfair-display', 'roboto-slab' ];

    /**
     * Section => its full token array, every key of schema() present.
     *
     * @var array<string, array>
     */
    public array $sections = [];

    /** Unchecked here: DesignStyles checks it when printing, so a stored value it rejects prints nothing. */
    public string $custom_css = '';

    /**
     * Shape: section => key => [ 'type' => 'int'|'enum'|'color'|'fill', 'default' => mixed,
     *   'enum' => array (enum only), 'min'/'max' => int (int only) ]
     *
     * Every default is empty: '' for a colour or an enum, null for an int. A colour is '', a hex colour or a
     * theme preset; colours.background may also be a gradient. An empty typography.base_size prints
     * DesignStyles::DEFAULT_BASE_SIZE, not the theme's size. The inputs.size token is an abstract step; the
     * renderer decides its height, in em. The inputs.label token prints no custom property: it is the checkout
     * wrapper's yaycheckout-checkout--floating-labels or yaycheckout-checkout--hidden-labels class. An empty checkboxes.checked fills a ticked box with the
     * primary colour. checkboxes.border and checkboxes.background colour an unticked checkbox, and each falls back
     * to the input's. radios styles loose radios, and its colours are the fallback for Payment and Shipping's
     * indicators; an empty radio colour falls back to the input's or, for checked, to the primary colour.
     * help_text sizes the help text under every kind of field; its colour is colours.muted.
     * colours.link and colours.link_hover colour the page's text links: the login and disclosure links, and any link in a paragraph, a
     * heading or the terms that no block gives a link colour of its own. An empty link_hover falls back
     * to link, and an empty link leaves each link its theme colour.
     */
    public static function schema(): array {
        return [
            'colours'    => [
                'primary'    => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'text'       => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'background' => [
                    'type'    => 'fill',
                    'default' => '',
                ],
                'border'     => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'muted'      => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'link'       => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'link_hover' => [
                    'type'    => 'color',
                    'default' => '',
                ],
            ],
            'typography' => [
                'font_family' => [
                    'type'    => 'enum',
                    'default' => '',
                    'enum'    => array_keys( self::FONT_FAMILIES ),
                ],
                'base_size'   => [
                    'type'    => 'int',
                    'default' => null,
                    'min'     => 10,
                    'max'     => 24,
                ],
            ],
            'inputs'     => [
                'style'            => [
                    'type'    => 'enum',
                    'default' => '',
                    'enum'    => [ '', 'boxed', 'underline' ],
                ],
                'size'             => [
                    'type'    => 'enum',
                    'default' => '',
                    'enum'    => [ '', 'small', 'medium', 'large' ],
                ],
                'radius'           => [
                    'type'    => 'int',
                    'default' => null,
                    'min'     => 0,
                    'max'     => 24,
                ],
                'background'       => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'text_color'       => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'label_color'      => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'border'           => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'focus_border'     => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'focus_background' => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'focus_style'      => [
                    'type'    => 'enum',
                    'default' => '',
                    'enum'    => [ '', 'border' ],
                ],
                'label'            => [
                    'type'    => 'enum',
                    'default' => '',
                    'enum'    => [ '', 'floating', 'hidden' ],
                ],
            ],
            'checkboxes' => [
                'size'         => [
                    'type'    => 'int',
                    'default' => null,
                    'min'     => 12,
                    'max'     => 24,
                ],
                'radius'       => [
                    'type'    => 'int',
                    'default' => null,
                    'min'     => 0,
                    'max'     => 12,
                ],
                'border_width' => [
                    'type'    => 'int',
                    'default' => null,
                    'min'     => 1,
                    'max'     => 3,
                ],
                'border'       => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'background'   => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'checked'      => [
                    'type'    => 'color',
                    'default' => '',
                ],
            ],
            'radios'     => [
                'size'         => [
                    'type'    => 'int',
                    'default' => null,
                    'min'     => 14,
                    'max'     => 24,
                ],
                'border_width' => [
                    'type'    => 'int',
                    'default' => null,
                    'min'     => 1,
                    'max'     => 3,
                ],
                'border'       => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'background'   => [
                    'type'    => 'color',
                    'default' => '',
                ],
                'checked'      => [
                    'type'    => 'color',
                    'default' => '',
                ],
            ],
            'help_text'  => [
                'size' => [
                    'type'    => 'int',
                    'default' => null,
                    'min'     => 10,
                    'max'     => 20,
                ],
            ],
        ];
    }

    /** True when the inputs' labels sit inside the box and float up once the field is focused or filled. */
    public function has_floating_labels(): bool {
        return 'floating' === ( $this->sections['inputs']['label'] ?? '' );
    }

    /** True when the inputs' labels are hidden from view; screen readers still read them. */
    public function has_hidden_labels(): bool {
        return 'hidden' === ( $this->sections['inputs']['label'] ?? '' );
    }

    /**
     * Projects a stored or imported value through schema(). Every section is always present: a missing section,
     * an invalid token or a key schema() doesn't name reads as its empty default.
     *
     * @param mixed $value Raw meta value; anything but an array reads as all empty.
     */
    public static function from_meta( $value ): self {
        $value  = is_array( $value ) ? $value : [];
        $design = new self();

        // Layouts saved before the rename store the focus border colour as inputs.focus.
        if ( is_array( $value['inputs'] ?? null ) && isset( $value['inputs']['focus'] ) && ! isset( $value['inputs']['focus_border'] ) ) {
            $value['inputs']['focus_border'] = $value['inputs']['focus'];
        }

        // Layouts saved before the rename store the input text colour as inputs.text.
        if ( is_array( $value['inputs'] ?? null ) && isset( $value['inputs']['text'] ) && ! isset( $value['inputs']['text_color'] ) ) {
            $value['inputs']['text_color'] = $value['inputs']['text'];
        }

        foreach ( self::schema() as $section => $rules ) {
            $design->sections[ $section ] = self::project_section( $rules, $value[ $section ] ?? null );
        }

        $design->custom_css = is_string( $value['custom_css'] ?? null ) ? $value['custom_css'] : '';

        return $design;
    }

    /** Every key always present, so the editor and an export never meet a partial shape. */
    public function to_meta(): array {
        return array_merge( $this->sections, [ 'custom_css' => $this->custom_css ] );
    }

    /** Whether $fill is a gradient, written out or a theme preset, rather than a colour. */
    public static function is_gradient( string $fill ): bool {
        return strlen( $fill ) <= self::GRADIENT_MAX_LENGTH
            && ( self::matches( self::GRADIENT_PATTERN, $fill ) || self::matches( self::PRESET_GRADIENT_PATTERN, $fill ) );
    }

    /**
     * Custom CSS as stored, or why it can't be. It is printed through
     * wp_add_inline_style(), which does not escape, so a closing </style> or
     * an opening <script> would break out of the stylesheet.
     *
     * @return string|\WP_Error
     */
    public static function sanitize_custom_css( string $css ) {
        if ( strlen( $css ) > self::CUSTOM_CSS_MAX_BYTES ) {
            return new \WP_Error(
                'invalid_arguments',
                sprintf(
                    /* translators: %d: size limit in KB */
                    __( 'Custom CSS must be under %d KB.', 'yaycheckout' ),
                    (int) ( self::CUSTOM_CSS_MAX_BYTES / 1000 )
                )
            );
        }

        if ( false !== strpos( $css, "\0" ) || preg_match( '#</style|<script#i', $css ) ) {
            return new \WP_Error( 'invalid_arguments', __( 'Custom CSS contains disallowed markup.', 'yaycheckout' ) );
        }

        return $css;
    }

    /**
     * JSON Schema for the meta's wp/v2 field, built from schema() so core refuses
     * an invalid token with a 400 and no second copy of the rules exists.
     */
    public static function rest_schema(): array {
        $properties = [];

        foreach ( self::schema() as $section => $keys ) {
            $tokens = [];
            foreach ( $keys as $key => $rule ) {
                $tokens[ $key ] = self::token_schema( $rule );
            }

            $properties[ $section ] = [
                'type'                 => 'object',
                'properties'           => $tokens,
                'required'             => array_keys( $tokens ),
                'additionalProperties' => false,
            ];
        }

        $properties['custom_css'] = [ 'type' => 'string' ];

        return [
            'type'                 => 'object',
            'properties'           => $properties,
            'required'             => array_keys( $properties ),
            'additionalProperties' => false,
        ];
    }

    /**
     * One section's tokens, each taken from $values when valid there and from the default otherwise.
     *
     * @param mixed $values The section's raw tokens; anything but an array reads as all defaults.
     */
    private static function project_section( array $rules, $values ): array {
        $values = is_array( $values ) ? $values : [];
        $clean  = [];

        foreach ( $rules as $key => $rule ) {
            $value = $values[ $key ] ?? null;

            $clean[ $key ] = self::is_valid( $value, $rule ) ? $value : $rule['default'];
        }

        return $clean;
    }

    private static function is_valid( $value, array $rule ): bool {
        switch ( $rule['type'] ) {
            // Empty is null, so 0 stays a value: a radius of 0 prints 0px.
            case 'int':
                return null === $value || ( is_int( $value ) && $value >= $rule['min'] && $value <= $rule['max'] );

            case 'enum':
                return in_array( $value, $rule['enum'], true );

            // Unset (''), a hex colour or a theme colour preset.
            case 'color':
                return is_string( $value )
                    && ( '' === $value || self::matches( self::HEX_COLOR_PATTERN, $value ) || self::matches( self::PRESET_COLOR_PATTERN, $value ) );

            // Any `color`, or a gradient.
            case 'fill':
                return self::is_valid( $value, [ 'type' => 'color' ] ) || ( is_string( $value ) && self::is_gradient( $value ) );
        }

        return false;
    }

    private static function matches( string $pattern, string $value ): bool {
        return 1 === preg_match( '/' . $pattern . '/D', $value );
    }

    /** One pattern matching any of $patterns, each keeping its own anchors. */
    private static function any_of( array $patterns ): string {
        return '(' . implode( ')|(', $patterns ) . ')';
    }

    private static function token_schema( array $rule ): array {
        switch ( $rule['type'] ) {
            case 'int':
                return [
                    'type'    => [ 'integer', 'null' ],
                    'minimum' => $rule['min'],
                    'maximum' => $rule['max'],
                ];

            case 'enum':
                return [
                    'type' => 'string',
                    'enum' => $rule['enum'],
                ];

            case 'color':
                return [
                    'type'    => 'string',
                    'pattern' => self::any_of( [ '^$', self::HEX_COLOR_PATTERN, self::PRESET_COLOR_PATTERN ] ),
                ];

            case 'fill':
                return [
                    'type'      => 'string',
                    'pattern'   => self::any_of(
                        [ '^$', self::HEX_COLOR_PATTERN, self::PRESET_COLOR_PATTERN, self::GRADIENT_PATTERN, self::PRESET_GRADIENT_PATTERN ]
                    ),
                    'maxLength' => self::GRADIENT_MAX_LENGTH,
                ];
        }

        return [];
    }
}
