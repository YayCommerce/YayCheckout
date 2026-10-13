import {
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalFontAppearanceControl as FontAppearanceControl,
	__experimentalFontFamilyControl as FontFamilyControl,
	__experimentalLetterSpacingControl as LetterSpacingControl,
	__experimentalTextDecorationControl as TextDecorationControl,
	__experimentalTextTransformControl as TextTransformControl,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
	FontSizePicker,
	getColorObjectByColorValue,
	LineHeightControl,
	useSettings,
} from '@wordpress/block-editor';
import {
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { compileCSS } from '@wordpress/style-engine';
import './summary-part-styles.css';

/**
 * The Label and Value styles of Order Summary Breakdown and Order Summary Total: core's typography
 * and text colour per part, under style.yaycheckout.<part>, compiled into one rule per cell type.
 * Mirrors SummaryPartStyles (includes/Renderer/SummaryPartStyles.php).
 */

export type SummaryPart = 'label' | 'value';

type StyleObject = Record< string, unknown >;

interface Preset {
	slug: string;
	name?: string;
	color?: string;
	size?: string;
	fontFamily?: string;
}

/** Each part and the cell it styles. */
const PARTS: [ SummaryPart, 'th' | 'td' ][] = [
	[ 'label', 'th' ],
	[ 'value', 'td' ],
];

const TYPOGRAPHY_KEYS = [
	'fontFamily',
	'fontSize',
	'fontStyle',
	'fontWeight',
	'lineHeight',
	'letterSpacing',
	'textDecoration',
	'textTransform',
] as const;

type TypographyKey = ( typeof TYPOGRAPHY_KEYS )[ number ];

function isPlainObject( value: unknown ): value is StyleObject {
	return typeof value === 'object' && value !== null && ! Array.isArray( value );
}

function getAt( style: StyleObject | undefined, path: string[] ): unknown {
	let cursor: unknown = style;

	for ( const key of path ) {
		if ( ! isPlainObject( cursor ) ) {
			return undefined;
		}
		cursor = cursor[ key ];
	}

	return cursor;
}

/** Drop empty values and empty nested objects, as payment/style-paths.ts does, so Copy/Paste styles stays tidy. */
function setAt( style: StyleObject | undefined, path: string[], value: unknown ): StyleObject | undefined {
	if ( path.length === 0 ) {
		return style;
	}

	const [ head, ...rest ] = path;
	const current = isPlainObject( style ) ? { ...style } : {};

	if ( rest.length === 0 ) {
		if ( value === undefined || value === null || value === '' ) {
			delete current[ head ];
		} else {
			current[ head ] = value;
		}
	} else {
		const nested = setAt( isPlainObject( current[ head ] ) ? ( current[ head ] as StyleObject ) : undefined, rest, value );
		if ( nested === undefined || Object.keys( nested ).length === 0 ) {
			delete current[ head ];
		} else {
			current[ head ] = nested;
		}
	}

	return Object.keys( current ).length === 0 ? undefined : current;
}

/** One part's style, only the keys the rules print. */
function partStyle( style: StyleObject | undefined, part: SummaryPart ): StyleObject {
	const value = getAt( style, [ 'yaycheckout', part ] );

	return isPlainObject( value ) ? { typography: value.typography, color: value.color } : {};
}

/** The Label and Value rules for the editor canvas, scoped to the block. */
export function summaryPartCSS( clientId: string, tableClass: string, style: StyleObject | undefined ): string {
	return PARTS.map( ( [ part, cell ] ) =>
		compileCSS( partStyle( style, part ), { selector: `#block-${ clientId } table.${ tableClass } tr > ${ cell }` } )
	).join( '' );
}

/** A theme preset list, from either a flat array or core's per-origin object. */
function presetList( value: unknown ): Preset[] {
	if ( Array.isArray( value ) ) {
		return value as Preset[];
	}

	return isPlainObject( value ) ? Object.values( value ).flatMap( ( list ) => ( Array.isArray( list ) ? list : [] ) ) : [];
}

const presetPrefix = ( kind: string ) => `var:preset|${ kind }|`;

/** A stored `var:preset|<kind>|<slug>` as the preset's value, for the control. */
function fromPreset< K extends keyof Preset >( value: unknown, kind: string, presets: Preset[], field: K ): string | undefined {
	if ( typeof value !== 'string' || value === '' ) {
		return undefined;
	}
	if ( ! value.startsWith( presetPrefix( kind ) ) ) {
		return value;
	}

	const slug = value.slice( presetPrefix( kind ).length );

	return presets.find( ( preset ) => preset.slug === slug )?.[ field ] ?? undefined;
}

interface SummaryPartPanelsProps {
	style: StyleObject | undefined;
	onChange: ( style: StyleObject | undefined ) => void;
	/** False hides the Label panel, keeping its values. */
	showLabel?: boolean;
}

/** The Styles tab's Label and Value panels, like core's Typography panel, each item resetting from the panel menu. */
export function SummaryPartPanels( { style, onChange, showLabel = true }: SummaryPartPanelsProps ) {
	const palettes = useMultipleOriginColorsAndGradients();
	const [ fontSizesSetting, fontFamiliesSetting ] = useSettings( 'typography.fontSizes', 'typography.fontFamilies' );
	const fontSizes = presetList( fontSizesSetting );
	const fontFamilies = presetList( fontFamiliesSetting );
	const colors = presetList(
		( palettes.colors as { colors?: Preset[] }[] | undefined )?.flatMap( ( origin ) => origin.colors ?? [] )
	);
	// Reset all calls back once per item before re-rendering, so each change must build on the last one.
	const latest = useRef( style );
	latest.current = style;

	const set = ( part: SummaryPart, path: string[], value: unknown ) => {
		latest.current = setAt( latest.current, [ 'yaycheckout', part, ...path ], value );
		onChange( latest.current );
	};

	const panel = ( part: SummaryPart, label: string ) => {
		const panelId = `yaycheckout-summary-${ part }`;
		const typography = ( key: TypographyKey ) => getAt( style, [ 'yaycheckout', part, 'typography', key ] );
		const setTypography = ( key: TypographyKey, value: unknown ) => set( part, [ 'typography', key ], value );
		const has = ( key: TypographyKey ) => {
			const value = typography( key );
			return value !== undefined && value !== null && value !== '';
		};
		const textColor = fromPreset( getAt( style, [ 'yaycheckout', part, 'color', 'text' ] ), 'color', colors, 'color' );
		const item = ( key: TypographyKey | 'appearance', itemLabel: string, control: JSX.Element ) => (
			<ToolsPanelItem
				key={ key }
				panelId={ panelId }
				label={ itemLabel }
				hasValue={ () => ( key === 'appearance' ? has( 'fontStyle' ) || has( 'fontWeight' ) : has( key ) ) }
				onDeselect={ () => {
					if ( key === 'appearance' ) {
						setTypography( 'fontStyle', undefined );
						setTypography( 'fontWeight', undefined );
					} else {
						setTypography( key, undefined );
					}
				} }
				isShownByDefault
			>
				{ control }
			</ToolsPanelItem>
		);

		return (
			<ToolsPanel
				key={ part }
				label={ label }
				panelId={ panelId }
				className="yaycheckout-summary-part-panel"
				resetAll={ () => set( part, [], undefined ) }
			>
				<div className="yaycheckout-summary-part-panel__colors">
					<ColorGradientSettingsDropdown
						__experimentalIsRenderedInSidebar
						panelId={ panelId }
						settings={ [
							{
								label: __( 'Text', 'yaycheckout' ),
								colorValue: textColor,
								onColorChange: ( next?: string ) => {
									const preset = next ? getColorObjectByColorValue( colors, next ) : undefined;
									set( part, [ 'color', 'text' ], preset?.slug ? `${ presetPrefix( 'color' ) }${ preset.slug }` : next || undefined );
								},
								clearable: true,
								isShownByDefault: true,
							},
						] }
						colors={ palettes.colors }
						disableCustomColors={ palettes.disableCustomColors }
						enableAlpha
					/>
				</div>
				{ fontFamilies.length > 0 &&
					item(
						'fontFamily',
						__( 'Font', 'yaycheckout' ),
						<FontFamilyControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							fontFamilies={ fontFamilies }
							value={ fromPreset( typography( 'fontFamily' ), 'font-family', fontFamilies, 'fontFamily' ) ?? '' }
							onChange={ ( next: string ) => {
								const slug = fontFamilies.find( ( family ) => family.fontFamily === next )?.slug;
								setTypography( 'fontFamily', slug ? `${ presetPrefix( 'font-family' ) }${ slug }` : next || undefined );
							} }
						/>
					) }
				{ item(
					'fontSize',
					__( 'Size', 'yaycheckout' ),
					<FontSizePicker
						__next40pxDefaultSize
						fontSizes={ fontSizes }
						value={ fromPreset( typography( 'fontSize' ), 'font-size', fontSizes, 'size' ) }
						onChange={ ( next: string | number | undefined, selected?: { slug?: string } ) => {
							const slug =
								selected?.slug ?? fontSizes.find( ( size ) => next !== undefined && size.size === String( next ) )?.slug;
							setTypography( 'fontSize', slug ? `${ presetPrefix( 'font-size' ) }${ slug }` : next );
						} }
						withReset={ false }
						withSlider
					/>
				) }
				{ item(
					'appearance',
					__( 'Appearance', 'yaycheckout' ),
					<FontAppearanceControl
						__next40pxDefaultSize
						value={ { fontStyle: typography( 'fontStyle' ), fontWeight: typography( 'fontWeight' ) } }
						onChange={ ( next: { fontStyle?: string; fontWeight?: string } ) => {
							setTypography( 'fontStyle', next.fontStyle );
							setTypography( 'fontWeight', next.fontWeight );
						} }
					/>
				) }
				{ item(
					'lineHeight',
					__( 'Line height', 'yaycheckout' ),
					<LineHeightControl
						__next40pxDefaultSize
						__unstableInputWidth="auto"
						value={ typography( 'lineHeight' ) }
						onChange={ ( next: string | undefined ) => setTypography( 'lineHeight', next ) }
					/>
				) }
				{ item(
					'letterSpacing',
					__( 'Letter spacing', 'yaycheckout' ),
					<LetterSpacingControl
						__next40pxDefaultSize
						__unstableInputWidth="auto"
						value={ typography( 'letterSpacing' ) }
						onChange={ ( next: string | undefined ) => setTypography( 'letterSpacing', next ) }
					/>
				) }
				{ item(
					'textDecoration',
					__( 'Decoration', 'yaycheckout' ),
					<TextDecorationControl
						value={ typography( 'textDecoration' ) }
						onChange={ ( next: string | undefined ) =>
							setTypography( 'textDecoration', next === typography( 'textDecoration' ) ? undefined : next )
						}
					/>
				) }
				{ item(
					'textTransform',
					__( 'Letter case', 'yaycheckout' ),
					<TextTransformControl
						value={ typography( 'textTransform' ) }
						onChange={ ( next: string | undefined ) =>
							setTypography( 'textTransform', next === typography( 'textTransform' ) ? undefined : next )
						}
					/>
				) }
			</ToolsPanel>
		);
	};

	return (
		<>
			{ showLabel && panel( 'label', __( 'Label', 'yaycheckout' ) ) }
			{ panel( 'value', __( 'Value', 'yaycheckout' ) ) }
		</>
	);
}
