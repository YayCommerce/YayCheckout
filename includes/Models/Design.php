<?php
namespace YayCheckout\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout design: the applied tokens and custom CSS. schema() describes every token;
 * from_option(), sanitize() and defaults() all walk it.
 */
class Design {

    public const OPTION_DESIGN = 'yaycheckout_design';

    public const CUSTOM_CSS_MAX_BYTES = 50000;

    public array $tokens = [];

    public string $custom_css = '';

    /**
     * Fonts the merchant can pick: token slug => CSS font-family, '' for the theme's font.
     * Google fonts that WordPress's Font Library ships, so a theme likely has them.
     */
    public const FONT_FAMILIES = [
        ''                 => '',
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

    /** ISO 8601 UTC; null when never saved. */
    public ?string $updated_at = null;

    /**
     * Shape: section => key => [ 'type' => 'bool'|'int'|'enum'|'hex_color', 'default' => mixed,
     *   'enum' => array (enum only), 'min'/'max' => int (int only) ]
     *
     * The inputs.size token is an abstract step; the renderer decides its px.
     * `enabled` false means theme default: the storefront prints nothing for that section,
     * and its values are kept for when it is turned back on.
     */
    public static function schema(): array {
        return [
            'colours'    => [
                'enabled'    => [
                    'type'    => 'bool',
                    'default' => false,
                ],
                'primary'    => [
                    'type'    => 'hex_color',
                    'default' => '#3858e9',
                ],
                'text'       => [
                    'type'    => 'hex_color',
                    'default' => '#171719',
                ],
                'background' => [
                    'type'    => 'hex_color',
                    'default' => '#ffffff',
                ],
                'border'     => [
                    'type'    => 'hex_color',
                    'default' => '#e4e4e7',
                ],
            ],
            'typography' => [
                'enabled'     => [
                    'type'    => 'bool',
                    'default' => false,
                ],
                'font_family' => [
                    'type'    => 'enum',
                    'default' => '',
                    'enum'    => array_keys( self::FONT_FAMILIES ),
                ],
                'base_size'   => [
                    'type'    => 'int',
                    'default' => 16,
                    'min'     => 10,
                    'max'     => 24,
                ],
            ],
            'inputs'     => [
                'enabled' => [
                    'type'    => 'bool',
                    'default' => false,
                ],
                'style'   => [
                    'type'    => 'enum',
                    'default' => 'boxed',
                    'enum'    => [ 'boxed', 'underline' ],
                ],
                'size'    => [
                    'type'    => 'enum',
                    'default' => 'medium',
                    'enum'    => [ 'small', 'medium', 'large' ],
                ],
                'radius'  => [
                    'type'    => 'int',
                    'default' => 10,
                    'min'     => 0,
                    'max'     => 24,
                ],
            ],
        ];
    }

    /** Every token at its schema default: what the SPA's Reset restores. */
    public static function defaults(): array {
        $defaults = [];

        foreach ( self::schema() as $section => $keys ) {
            foreach ( $keys as $key => $rule ) {
                $defaults[ $section ][ $key ] = $rule['default'];
            }
        }

        return $defaults;
    }

    /**
     * Builds from the stored option value, projected through the schema.
     * Walks the schema, not the stored array: a stale or invalid stored token
     * reads back as its default instead of failing the SPA's strict wire schema.
     * Keys the schema no longer has (presets, preset_id, the buttons and spacing
     * sections) are ignored, and the next save drops them.
     *
     * @param mixed $saved Raw option value; anything but an array is treated as empty.
     */
    public static function from_option( $saved ): self {
        $saved  = is_array( $saved ) ? $saved : [];
        $design = new self();

        $design->tokens     = self::project( $saved['tokens'] ?? null );
        $design->custom_css = is_string( $saved['custom_css'] ?? null ) ? $saved['custom_css'] : '';
        $design->updated_at = is_string( $saved['updated_at'] ?? null ) && '' !== $saved['updated_at']
            ? $saved['updated_at']
            : null;

        return $design;
    }

    /**
     * Validates a full { tokens, custom_css } payload. Both keys are required; any
     * other key, such as an old client's preset_id, is ignored.
     *
     * @return array|\WP_Error [ 'tokens', 'custom_css' ] clean, or the first failure.
     */
    public function sanitize_input( array $input ) {
        foreach ( [ 'tokens', 'custom_css' ] as $key ) {
            if ( ! array_key_exists( $key, $input ) ) {
                return self::invalid( $key );
            }
        }

        if ( ! is_array( $input['tokens'] ) ) {
            return self::invalid( 'tokens' );
        }

        $tokens = self::sanitize( $input['tokens'] );

        if ( $tokens instanceof \WP_Error ) {
            return $tokens;
        }

        if ( ! is_string( $input['custom_css'] ) ) {
            return self::invalid( 'custom_css' );
        }

        $custom_css = self::sanitize_custom_css( $input['custom_css'] );

        if ( $custom_css instanceof \WP_Error ) {
            return $custom_css;
        }

        return [
            'tokens'     => $tokens,
            'custom_css' => $custom_css,
        ];
    }

    /**
     * A layout's sparse layer, as stored in Layout::META_DESIGN: { tokens?: { section: every key }, custom_css? }.
     * Forgiving: a section missing a key or holding an invalid value, unknown keys and
     * unsafe or empty CSS are dropped, so the layout falls back to global for them.
     *
     * @param mixed $raw Stored or imported value; anything but an array reads as no layer.
     */
    public static function project_layer( $raw ): array {
        $raw    = is_array( $raw ) ? $raw : [];
        $tokens = is_array( $raw['tokens'] ?? null ) ? $raw['tokens'] : [];
        $layer  = [];

        foreach ( self::schema() as $section => $keys ) {
            $clean = self::layer_section( $tokens[ $section ] ?? null, $keys );

            if ( null !== $clean ) {
                $layer['tokens'][ $section ] = $clean;
            }
        }

        $css = $raw['custom_css'] ?? null;

        if ( is_string( $css ) && '' !== $css && is_string( self::sanitize_custom_css( $css ) ) ) {
            $layer['custom_css'] = $css;
        }

        return $layer;
    }

    /**
     * Strict twin of project_layer() for a write: the first invalid section, key or CSS fails.
     *
     * @return array|\WP_Error The same shape project_layer() returns, or the first failure.
     */
    public static function validate_layer( array $input ) {
        $tokens = $input['tokens'] ?? [];

        if ( ! is_array( $tokens ) ) {
            return self::invalid( 'tokens' );
        }

        foreach ( self::schema() as $section => $keys ) {
            if ( ! array_key_exists( $section, $tokens ) ) {
                continue;
            }

            if ( ! is_array( $tokens[ $section ] ) ) {
                return self::invalid( $section );
            }

            $key = self::invalid_key( $tokens[ $section ], $keys );

            if ( null !== $key ) {
                return self::invalid( $section . '.' . $key );
            }
        }

        $css = $input['custom_css'] ?? '';

        if ( ! is_string( $css ) ) {
            return self::invalid( 'custom_css' );
        }

        $css = self::sanitize_custom_css( $css );

        if ( $css instanceof \WP_Error ) {
            return $css;
        }

        return self::project_layer( $input );
    }

    /** This design with a layout's layer on top: each layer section replaces the global one whole, and layer CSS prints after global CSS. */
    public function with_layer( array $layer ): self {
        $design = clone $this;

        foreach ( $layer['tokens'] ?? [] as $section => $values ) {
            $design->tokens[ $section ] = $values;
        }

        if ( isset( $layer['custom_css'] ) ) {
            $design->custom_css = '' === $design->custom_css ? $layer['custom_css'] : $design->custom_css . "\n" . $layer['custom_css'];
        }

        return $design;
    }

    /** A layer section with every schema key valid, in schema order; null when any is missing or invalid. */
    private static function layer_section( $values, array $keys ): ?array {
        if ( ! is_array( $values ) || null !== self::invalid_key( $values, $keys ) ) {
            return null;
        }

        return array_intersect_key( array_replace( $keys, $values ), $keys );
    }

    /** The first schema key a layer section lacks or holds an invalid value for; null when the section is complete. */
    private static function invalid_key( array $values, array $keys ): ?string {
        foreach ( $keys as $key => $rule ) {
            if ( ! array_key_exists( $key, $values ) || ! self::is_valid( $values[ $key ], $rule ) ) {
                return $key;
            }
        }

        return null;
    }

    /** Every schema token, taken from $tokens when valid there and from the default otherwise. */
    private static function project( $tokens ): array {
        $tokens = is_array( $tokens ) ? $tokens : [];
        $clean  = [];

        foreach ( self::schema() as $section => $keys ) {
            foreach ( $keys as $key => $rule ) {
                $value = $tokens[ $section ][ $key ] ?? null;

                $clean[ $section ][ $key ] = self::is_valid( $value, $rule ) ? $value : $rule['default'];
            }
        }

        return $clean;
    }

    /**
     * Whitelists and type-checks an incoming tokens array against the schema.
     * Unknown keys are dropped; types are strict (a string "true" is a bug,
     * not coerced); a missing key becomes its default, so partial updates
     * must merge with stored values first.
     *
     * @return array|\WP_Error Clean tokens, or the first validation failure.
     */
    public static function sanitize( array $input ) {
        $clean = [];

        foreach ( self::schema() as $section => $keys ) {
            if ( array_key_exists( $section, $input ) && ! is_array( $input[ $section ] ) ) {
                return self::invalid( $section );
            }

            foreach ( $keys as $key => $rule ) {
                if ( ! isset( $input[ $section ] ) || ! array_key_exists( $key, $input[ $section ] ) ) {
                    $clean[ $section ][ $key ] = $rule['default'];
                    continue;
                }

                $value = $input[ $section ][ $key ];

                if ( ! self::is_valid( $value, $rule ) ) {
                    return self::invalid( $section . '.' . $key );
                }

                $clean[ $section ][ $key ] = $value;
            }
        }

        return $clean;
    }

    /**
     * Rejects custom CSS that could not be emitted safely. The output path is
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

    /** The option value, exactly as from_option() reads it back. */
    public function to_option(): array {
        return [
            'tokens'     => $this->tokens,
            'custom_css' => $this->custom_css,
            'updated_at' => $this->updated_at,
        ];
    }

    /** The shape both GET/POST /design and the localize bootstrap emit; defaults are what Reset restores. */
    public function to_array(): array {
        return [
            'tokens'     => $this->tokens,
            'custom_css' => $this->custom_css,
            'updated_at' => $this->updated_at,
            'defaults'   => self::defaults(),
        ];
    }

    private static function is_valid( $value, array $rule ): bool {
        switch ( $rule['type'] ) {
            case 'bool':
                return is_bool( $value );

            case 'int':
                return is_int( $value ) && self::in_range( $value, $rule );

            case 'enum':
                return in_array( $value, $rule['enum'], true );

            case 'hex_color':
                return is_string( $value ) && 1 === preg_match( '/^#[0-9A-Fa-f]{6}$/D', $value );
        }

        return false;
    }

    private static function in_range( $value, array $rule ): bool {
        return $value >= $rule['min'] && $value <= $rule['max'];
    }

    private static function invalid( string $path ): \WP_Error {
        return new \WP_Error(
            'invalid_arguments',
            /* translators: %s: dotted path of the token, e.g. colours.primary */
            sprintf( __( 'Invalid value for %s.', 'yaycheckout' ), $path )
        );
    }
}
