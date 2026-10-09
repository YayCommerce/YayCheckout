<?php
namespace YayCheckout\Models;

defined( 'ABSPATH' ) || exit;

/**
 * One layout's override of the global Design: per section, its own tokens or null to
 * follow the Design page, plus custom CSS printed after the global custom CSS.
 * Stored as Layout::META_DESIGN; to_meta() is also its wire and export shape.
 */
final class LayoutDesign {

    /**
     * Section => its full token array (including `enabled`), or null for the global design.
     *
     * @var array<string, array|null>
     */
    public array $sections = [];

    /** Unchecked here: DesignStyles checks it when printing, so a stored value it rejects prints nothing. */
    public string $custom_css = '';

    /**
     * Design::schema() with one difference: the background is a `fill`, so a layout's page can take a
     * gradient. The global design's background stays a hex colour.
     */
    public static function schema(): array {
        $schema = Design::schema();

        $schema['colours']['background']['type'] = 'fill';

        return $schema;
    }

    /** No section overridden and no custom CSS. */
    public static function none(): self {
        $design = new self();

        $design->sections = array_fill_keys( array_keys( Design::schema() ), null );

        return $design;
    }

    /**
     * Projects a stored or imported value through schema(), as Design::from_option() does:
     * a section that isn't an array follows the global design, and an invalid token reads as its default.
     *
     * @param mixed $value Raw meta value; anything but an array is none().
     */
    public static function from_meta( $value ): self {
        $value  = is_array( $value ) ? $value : [];
        $design = self::none();

        foreach ( self::schema() as $section => $rules ) {
            if ( is_array( $value[ $section ] ?? null ) ) {
                $design->sections[ $section ] = Design::project_section( $rules, $value[ $section ] );
            }
        }

        $design->custom_css = is_string( $value['custom_css'] ?? null ) ? $value['custom_css'] : '';

        return $design;
    }

    /** Every key always present, so the editor and an export never meet a partial shape. */
    public function to_meta(): array {
        return array_merge( $this->sections, [ 'custom_css' => $this->custom_css ] );
    }

    /** The global tokens with each overridden section swapped in. */
    public function tokens_over( array $tokens ): array {
        foreach ( $this->sections as $section => $values ) {
            if ( null !== $values ) {
                $tokens[ $section ] = $values;
            }
        }

        return $tokens;
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
                'type'                 => [ 'object', 'null' ],
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

    private static function token_schema( array $rule ): array {
        switch ( $rule['type'] ) {
            case 'bool':
                return [ 'type' => 'boolean' ];

            case 'int':
                return [
                    'type'    => 'integer',
                    'minimum' => $rule['min'],
                    'maximum' => $rule['max'],
                ];

            case 'enum':
                return [
                    'type' => 'string',
                    'enum' => $rule['enum'],
                ];

            case 'hex_color':
                return [
                    'type'    => 'string',
                    'pattern' => Design::HEX_COLOR_PATTERN,
                ];

            case 'fill':
                return [
                    'type'      => 'string',
                    'pattern'   => '(' . Design::HEX_COLOR_PATTERN . ')|(' . Design::GRADIENT_PATTERN . ')',
                    'maxLength' => Design::GRADIENT_MAX_LENGTH,
                ];
        }

        return [];
    }
}
