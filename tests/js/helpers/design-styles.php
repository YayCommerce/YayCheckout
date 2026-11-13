<?php
/**
 * Prints, as JSON, what PHP makes of each raw design read from STDIN: the projected tokens, the font lists the
 * editor is given (BlockRegistry) and DesignStyles::css(). For design-css.test.ts.
 */

define( 'ABSPATH', __DIR__ );

spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 === strncmp( 'YayCheckout\\', $class_name, 12 ) ) {
			require_once dirname( __DIR__, 3 ) . '/includes/' . str_replace( '\\', '/', substr( $class_name, 12 ) ) . '.php';
		}
	}
);

use YayCheckout\Models\LayoutDesign;
use YayCheckout\Renderer\DesignStyles;

$yay_designs = json_decode( (string) file_get_contents( 'php://stdin' ), true );
$yay_out     = [
	'fonts'   => [
		'fontFamilies' => LayoutDesign::FONT_FAMILIES,
		'serifFonts'   => LayoutDesign::SERIF_FONTS,
		'fontStacks'   => LayoutDesign::FONT_STACKS,
	],
	'designs' => [],
];

foreach ( $yay_designs as $yay_raw ) {
	$yay_design           = LayoutDesign::from_meta( $yay_raw );
	$yay_out['designs'][] = [
		'tokens' => $yay_design->to_meta(),
		'css'    => ( new DesignStyles() )->css( $yay_design ),
	];
}

echo json_encode( $yay_out );
