<?php
namespace YayCheckout\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout design: the applied tokens, custom CSS, the preset they were taken
 * from, and every preset (built-in plus merchant-saved). Pure entity: no WordPress calls.
 *
 * The schema() is the single description of every token; from_option(), sanitize()
 * and defaults() all walk it. The Default preset is the schema defaults, so
 * "Reset to theme" in the SPA is a form reset to presets()[0] — no server call.
 *
 * Applying a preset copies its tokens into $tokens; $preset_id only records
 * which one, so the editor can show edits relative to it. Rendering never
 * resolves the id — a deleted preset changes nothing on the storefront.
 */
class Design {

    public const OPTION_DESIGN = 'yaycheckout_design';

    public const CUSTOM_CSS_MAX_BYTES = 50000;

    public array $tokens = [];

    public string $custom_css = '';

    /** Id of the preset the tokens were copied from; null once that preset is gone. */
    public ?string $preset_id = null;

    /**
     * Fonts the merchant can pick; '' means the theme's own font. Widely used
     * Google fonts that WordPress's Font Library ships, so a theme is likely to
     * have them already. Loading them on the storefront is a separate concern.
     */
    public const FONT_FAMILIES = [
        '',
        'Inter',
        'Roboto',
        'Open Sans',
        'Lato',
        'Montserrat',
        'Poppins',
        'Nunito',
        'Source Sans 3',
        'Manrope',
        'Merriweather',
        'Playfair Display',
        'Roboto Slab',
    ];

    /** Spacing multipliers the merchant can pick, as floats so 1.0 and 1 stay one value. */
    public const SPACING_SCALES = [ 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0 ];

    /** ISO 8601 UTC; null when never saved. */
    public ?string $updated_at = null;

    /** Merchant-saved presets as stored: { id, name, tokens, created_at }. Written by Pro; always [] on Free. */
    public array $user_presets = [];

    /** Built-ins followed by user presets, each { id, name, builtin, tokens } — the list the SPA shows. */
    public array $presets = [];

    /**
     * Shape: section => key => [ 'type' => 'bool'|'int'|'enum'|'hex_color', 'default' => mixed,
     *   'enum' => array (enum only), 'min'/'max' => int (int only) ]
     *
     * The layout-wide corner radius is spacing.radius; buttons.radius and
     * inputs.radius are stored on their own so the editor can push the shared
     * value into them and still let each be tuned afterwards. Both spacing.scale
     * and inputs.size are abstract steps; what they mean in px is decided where
     * they are rendered.
     */
    public static function schema(): array {
        return [
            'colours'    => [
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
                'inherit'     => [
                    'type'    => 'bool',
                    'default' => true,
                ],
                'font_family' => [
                    'type'    => 'enum',
                    'default' => '',
                    'enum'    => self::FONT_FAMILIES,
                ],
                'base_size'   => [
                    'type'    => 'int',
                    'default' => 16,
                    'min'     => 10,
                    'max'     => 24,
                ],
            ],
            'spacing'    => [
                'scale'  => [
                    'type'    => 'enum',
                    'default' => 1.0,
                    'enum'    => self::SPACING_SCALES,
                ],
                'radius' => [
                    'type'    => 'int',
                    'default' => 10,
                    'min'     => 0,
                    'max'     => 24,
                ],
            ],
            'buttons'    => [
                'style'  => [
                    'type'    => 'enum',
                    'default' => 'fill',
                    'enum'    => [ 'fill', 'outline' ],
                ],
                'radius' => [
                    'type'    => 'int',
                    'default' => 10,
                    'min'     => 0,
                    'max'     => 24,
                ],
            ],
            'inputs'     => [
                'style'  => [
                    'type'    => 'enum',
                    'default' => 'boxed',
                    'enum'    => [ 'boxed', 'underline' ],
                ],
                'size'   => [
                    'type'    => 'enum',
                    'default' => 'medium',
                    'enum'    => [ 'small', 'medium', 'large' ],
                ],
                'radius' => [
                    'type'    => 'int',
                    'default' => 10,
                    'min'     => 0,
                    'max'     => 24,
                ],
            ],
        ];
    }

    /** Every token at its schema default — also the Default preset. */
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
     * Built-in presets in display order. Default is the schema defaults; the
     * others override only the keys that differ. Names are translated here
     * so the wire carries display strings.
     *
     * @return array<int, array{id: string, name: string, builtin: bool, tokens: array}>
     */
    public static function presets(): array {
        $default = self::defaults();

        return [
            [
                'id'      => 'default',
                'name'    => __( 'Default', 'yaycheckout' ),
                'builtin' => true,
                'tokens'  => $default,
            ],
            [
                'id'      => 'dark',
                'name'    => __( 'Dark', 'yaycheckout' ),
                'builtin' => true,
                'tokens'  => array_replace_recursive(
                    $default,
                    [
                        'colours' => [
                            'primary'    => '#3858e9',
                            'text'       => '#fafafa',
                            'background' => '#0a0a0a',
                            'border'     => '#404040',
                        ],
                    ]
                ),
            ],
            [
                'id'      => 'b2b',
                'name'    => __( 'B2B', 'yaycheckout' ),
                'builtin' => true,
                'tokens'  => array_replace_recursive(
                    $default,
                    [
                        'colours'    => [
                            'primary'    => '#171719',
                            'text'       => '#171719',
                            'background' => '#f4f4f5',
                            'border'     => '#c7c7ca',
                        ],
                        'typography' => [ 'base_size' => 15 ],
                        'spacing'    => [ 'radius' => 2 ],
                        'buttons'    => [
                            'style'  => 'outline',
                            'radius' => 2,
                        ],
                        'inputs'     => [
                            'size'   => 'small',
                            'radius' => 2,
                        ],
                    ]
                ),
            ],
        ];
    }

    /**
     * Builds from the stored option value, projected through the schema.
     * Walks the schema, not the stored array: a stale or invalid stored token
     * reads back as its default instead of failing the SPA's strict wire schema.
     * User presets get the same treatment; one that lost its id or name is dropped.
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

        foreach ( is_array( $saved['presets'] ?? null ) ? $saved['presets'] : [] as $preset ) {
            if ( ! is_array( $preset ) || ! self::is_id( $preset['id'] ?? null ) || ! is_string( $preset['name'] ?? null ) ) {
                continue;
            }

            $design->user_presets[] = [
                'id'         => $preset['id'],
                'name'       => $preset['name'],
                'tokens'     => self::project( $preset['tokens'] ?? null ),
                'created_at' => is_string( $preset['created_at'] ?? null ) ? $preset['created_at'] : null,
            ];
        }

        $design->presets = array_merge(
            self::presets(),
            array_map(
                fn( array $preset ) => [
                    'id'      => $preset['id'],
                    'name'    => $preset['name'],
                    'builtin' => false,
                    'tokens'  => $preset['tokens'],
                ],
                $design->user_presets
            )
        );

        $preset_id         = $saved['preset_id'] ?? null;
        $design->preset_id = $design->has_preset( $preset_id ) ? $preset_id : null;

        return $design;
    }

    /** Whether $id names a preset in the current list; null is never one. */
    public function has_preset( $id ): bool {
        return self::is_id( $id ) && in_array( $id, array_column( $this->presets, 'id' ), true );
    }

    /** Every schema token, taken from $tokens when valid there and from the default otherwise. */
    private static function project( $tokens ): array {
        $tokens = is_array( $tokens ) ? $tokens : [];
        $clean  = [];

        foreach ( self::schema() as $section => $keys ) {
            foreach ( $keys as $key => $rule ) {
                $value = self::coerce( $tokens[ $section ][ $key ] ?? null, $rule );

                $clean[ $section ][ $key ] = self::is_valid( $value, $rule ) ? $value : $rule['default'];
            }
        }

        return $clean;
    }

    private static function is_id( $id ): bool {
        return is_string( $id ) && '' !== $id;
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

                $value = self::coerce( $input[ $section ][ $key ], $rule );

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

    /** The shape both GET/POST /design and the localize bootstrap emit. */
    public function to_array(): array {
        return [
            'tokens'     => $this->tokens,
            'preset_id'  => $this->preset_id,
            'custom_css' => $this->custom_css,
            'updated_at' => $this->updated_at,
            'presets'    => $this->presets,
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
                return is_string( $value ) && 1 === preg_match( '/^#[0-9A-Fa-f]{6}$/', $value );
        }

        return false;
    }

    /**
     * Lines a value up with its rule before validation. JSON has no 1.0, so a
     * whole-number scale arrives as int; a float enum wants it back as float.
     */
    private static function coerce( $value, array $rule ) {
        if ( 'enum' === $rule['type'] && is_int( $value ) && is_float( $rule['enum'][0] ) ) {
            return (float) $value;
        }

        return $value;
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
