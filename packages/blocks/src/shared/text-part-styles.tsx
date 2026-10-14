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
import { getAt, hasValue, isPlainObject, setAt, type StyleObject } from './style-path';
import './tools-panel.css';

/**
 * Per-part text styles: core's typography and text colour for one part of a block, stored under
 * style.yaycheckout.<part> and compiled into one rule per part. Used by the summary Label and Value
 * styles (summary-part-styles.tsx) and the section Title and Description styles (section-heading.tsx).
 */

interface Preset {
	slug: string;
	color?: string;
	size?: string;
	fontFamily?: string;
}

type TypographyKey =
	| 'fontFamily'
	| 'fontSize'
	| 'fontStyle'
	| 'fontWeight'
	| 'lineHeight'
	| 'letterSpacing'
	| 'textDecoration'
	| 'textTransform';

/** One part's style, only the keys the rules print. Mirrors SummaryPartStyles::KEYS in PHP. */
export function partStyle( style: StyleObject | undefined, part: string ): StyleObject {
	const value = getAt( style, [ 'yaycheckout', part ] );

	return isPlainObject( value ) ? { typography: value.typography, color: value.color } : {};
}

/** One part's rule for the editor canvas; '' when the part sets nothing. */
export function textPartCSS( style: StyleObject | undefined, part: string, selector: string ): string {
	return compileCSS( partStyle( style, part ), { selector } );
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
function fromPreset( value: unknown, kind: string, presets: Preset[], field: 'color' | 'size' | 'fontFamily' ): string | undefined {
	if ( typeof value !== 'string' || value === '' ) {
		return undefined;
	}
	if ( ! value.startsWith( presetPrefix( kind ) ) ) {
		return value;
	}

	const slug = value.slice( presetPrefix( kind ).length );

	return presets.find( ( preset ) => preset.slug === slug )?.[ field ];
}

export interface TextPart {
	key: string;
	label: string;
}

interface TextPartPanelsProps {
	/** The parts to show, in order. A part left out keeps its stored values. */
	parts: TextPart[];
	/** Prefix of each panel's id; the part key is appended. */
	panelIdPrefix: string;
	style: StyleObject | undefined;
	onChange: ( style: StyleObject | undefined ) => void;
}

/** One Styles tab panel per part, like core's Typography panel, each item resetting from the panel menu. */
export function TextPartPanels( { parts, panelIdPrefix, style, onChange }: TextPartPanelsProps ) {
	const palettes = useMultipleOriginColorsAndGradients();
	const [ fontSizesSetting, fontFamiliesSetting ] = useSettings( 'typography.fontSizes', 'typography.fontFamilies' );
	const fontSizes = presetList( fontSizesSetting );
	const fontFamilies = presetList( fontFamiliesSetting );
	const colors = ( palettes.colors as { colors?: Preset[] }[] | undefined )?.flatMap( ( origin ) => origin.colors ?? [] ) ?? [];
	// Reset all calls back once per item before re-rendering, so each change must build on the last one.
	const latest = useRef( style );
	latest.current = style;

	const set = ( part: string, path: string[], value: unknown ) => {
		latest.current = setAt( latest.current, [ 'yaycheckout', part, ...path ], value );
		onChange( latest.current );
	};

	const panel = ( part: string, label: string ) => {
		const panelId = `${ panelIdPrefix }-${ part }`;
		const typography = ( key: TypographyKey ) => getAt( style, [ 'yaycheckout', part, 'typography', key ] );
		const setTypography = ( key: TypographyKey, value: unknown ) => set( part, [ 'typography', key ], value );
		const has = ( key: TypographyKey ) => hasValue( typography( key ) );
		// Decoration and Letter case clear when the picked value is picked again.
		const toggle = ( key: TypographyKey ) => ( next: string | undefined ) =>
			setTypography( key, next === typography( key ) ? undefined : next );
		const textColor = fromPreset( getAt( style, [ 'yaycheckout', part, 'color', 'text' ] ), 'color', colors, 'color' );
		const item = ( keys: TypographyKey[], itemLabel: string, control: JSX.Element ) => (
			<ToolsPanelItem
				key={ keys.join() }
				panelId={ panelId }
				label={ itemLabel }
				hasValue={ () => keys.some( has ) }
				onDeselect={ () => keys.forEach( ( key ) => setTypography( key, undefined ) ) }
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
				className="yaycheckout-tools-panel"
				resetAll={ () => set( part, [], undefined ) }
			>
				<div className="yaycheckout-tools-panel__colors">
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
						[ 'fontFamily' ],
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
					[ 'fontSize' ],
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
					[ 'fontStyle', 'fontWeight' ],
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
					[ 'lineHeight' ],
					__( 'Line height', 'yaycheckout' ),
					<LineHeightControl
						__next40pxDefaultSize
						__unstableInputWidth="auto"
						value={ typography( 'lineHeight' ) }
						onChange={ ( next: string | undefined ) => setTypography( 'lineHeight', next ) }
					/>
				) }
				{ item(
					[ 'letterSpacing' ],
					__( 'Letter spacing', 'yaycheckout' ),
					<LetterSpacingControl
						__next40pxDefaultSize
						__unstableInputWidth="auto"
						value={ typography( 'letterSpacing' ) }
						onChange={ ( next: string | undefined ) => setTypography( 'letterSpacing', next ) }
					/>
				) }
				{ item(
					[ 'textDecoration' ],
					__( 'Decoration', 'yaycheckout' ),
					<TextDecorationControl
						value={ typography( 'textDecoration' ) }
						onChange={ toggle( 'textDecoration' ) }
					/>
				) }
				{ item(
					[ 'textTransform' ],
					__( 'Letter case', 'yaycheckout' ),
					<TextTransformControl
						value={ typography( 'textTransform' ) }
						onChange={ toggle( 'textTransform' ) }
					/>
				) }
			</ToolsPanel>
		);
	};

	return <>{ parts.map( ( { key, label } ) => panel( key, label ) ) }</>;
}
