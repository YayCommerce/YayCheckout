import apiFetch from '@wordpress/api-fetch';
import {
	store as blockEditorStore,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
	getColorClassName,
} from '@wordpress/block-editor';
import {
	Flex,
	PanelBody,
	RangeControl,
	SelectControl,
	TextareaControl,
	__experimentalToolsPanel as ToolsPanel,
} from '@wordpress/components';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { PluginSidebar, PluginSidebarMoreMenuItem, store as editorStore } from '@wordpress/editor';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useCanvasStyle } from '../shared/canvas-style';
import { DEFAULT_BASE_SIZE, designCss, isGradient } from '../shared/design-css';
import { editorData, type DesignRule, type EditorDesign } from '../shared/editor-data';
import { useIsLayoutEditor } from '../shared/layout-editor';
import { errorMessage } from '../shared/error-message';
import './layout-design.css';

export const SIDEBAR = 'yaycheckout-layout-design';
const CSS_LOCK = 'yaycheckout-layout-design-css';

type SectionTokens = Record< string, string | number | null >;

/** Layout::META_DESIGN as LayoutDesign::to_meta() shapes it: every section present, an empty token '' or null. */
export type LayoutDesignMeta = {
	colours: SectionTokens;
	typography: SectionTokens;
	inputs: SectionTokens;
	checkboxes: SectionTokens;
	radios: SectionTokens;
	help_text: SectionTokens;
	custom_css: string;
};

type Section = 'colours' | 'typography' | 'inputs' | 'checkboxes' | 'radios' | 'help_text';

const SECTIONS: Record< Section, string > = {
	colours: __( 'Colours', 'yaycheckout' ),
	typography: __( 'Typography', 'yaycheckout' ),
	inputs: __( 'Inputs', 'yaycheckout' ),
	checkboxes: __( 'Checkboxes', 'yaycheckout' ),
	radios: __( 'Radios', 'yaycheckout' ),
	help_text: __( 'Help text', 'yaycheckout' ),
};

/** Each section's colour panel. A section of colours only shows that panel alone, under the section's name. */
const COLOUR_PANELS: Record< Section, string > = {
	colours: __( 'Colours', 'yaycheckout' ),
	typography: __( 'Text colours', 'yaycheckout' ),
	inputs: __( 'Input colours', 'yaycheckout' ),
	checkboxes: __( 'Checkbox colours', 'yaycheckout' ),
	radios: __( 'Radio colours', 'yaycheckout' ),
	help_text: __( 'Help text colour', 'yaycheckout' ),
};

const TOKEN_LABELS: Record< string, string > = {
	primary: __( 'Primary', 'yaycheckout' ),
	text: __( 'Text', 'yaycheckout' ),
	background: __( 'Background', 'yaycheckout' ),
	border: __( 'Border', 'yaycheckout' ),
	muted: __( 'Muted', 'yaycheckout' ),
	font_family: __( 'Font', 'yaycheckout' ),
	base_size: __( 'Base size', 'yaycheckout' ),
	style: __( 'Style', 'yaycheckout' ),
	size: __( 'Size', 'yaycheckout' ),
	radius: __( 'Radius', 'yaycheckout' ),
	focus_border: __( 'Focus border', 'yaycheckout' ),
	focus_background: __( 'Focus background', 'yaycheckout' ),
	focus_style: __( 'Focus style', 'yaycheckout' ),
	label: __( 'Label', 'yaycheckout' ),
	text_color: __( 'Input text', 'yaycheckout' ),
	label_color: __( 'Label text', 'yaycheckout' ),
	border_width: __( 'Border width', 'yaycheckout' ),
	checked: __( 'Checked', 'yaycheckout' ),
	color: __( 'Colour', 'yaycheckout' ),
};

const ENUM_LABELS: Record< string, string > = {
	'': __( 'Theme default', 'yaycheckout' ),
	boxed: __( 'Boxed', 'yaycheckout' ),
	underline: __( 'Underline', 'yaycheckout' ),
	small: __( 'Small', 'yaycheckout' ),
	medium: __( 'Medium', 'yaycheckout' ),
	large: __( 'Large', 'yaycheckout' ),
	floating: __( 'Floating', 'yaycheckout' ),
	hidden: __( 'Hidden', 'yaycheckout' ),
};

/** Focus style names what the shopper sees: the theme's ring thickens the border; `border` drops the ring. */
const FOCUS_STYLE_LABELS: Record< string, string > = {
	'': __( 'Thick border', 'yaycheckout' ),
	border: __( 'Thin border', 'yaycheckout' ),
};

function isTokens( value: unknown ): value is Record< string, unknown > {
	return null !== value && 'object' === typeof value && ! Array.isArray( value );
}

/** One section with every key of its rules, each from `raw` when present there and from the empty default otherwise. */
function sectionTokens( rules: Record< string, DesignRule >, raw: unknown ): SectionTokens {
	const values = isTokens( raw ) ? raw : {};

	return Object.fromEntries(
		Object.entries( rules ).map( ( [ token, rule ] ) => [
			token,
			token in values ? ( values[ token ] as string | number | null ) : rule.default,
		] )
	);
}

/** The meta as the editor holds it, with every key present. The server projects it on save. */
function normalize( design: EditorDesign, raw: unknown ): LayoutDesignMeta {
	const meta = isTokens( raw ) ? raw : {};

	return {
		colours: sectionTokens( design.schema.colours, meta.colours ),
		typography: sectionTokens( design.schema.typography, meta.typography ),
		inputs: sectionTokens( design.schema.inputs, meta.inputs ),
		checkboxes: sectionTokens( design.schema.checkboxes, meta.checkboxes ),
		radios: sectionTokens( design.schema.radios, meta.radios ),
		help_text: sectionTokens( design.schema.help_text, meta.help_text ),
		custom_css: 'string' === typeof meta.custom_css ? meta.custom_css : '',
	};
}

/** The edited design and its setter; writing marks the post dirty, and Save stores it with the markup. */
function useLayoutDesign( design: EditorDesign ): [ LayoutDesignMeta, ( next: LayoutDesignMeta ) => void ] {
	const raw = useSelect(
		( selectStore ) =>
			( selectStore( editorStore ).getEditedPostAttribute( 'meta' ) as Record< string, unknown > | undefined )?.[
				design.metaKey
			],
		[ design.metaKey ]
	);
	const { editPost } = useDispatch( editorStore );

	return [ normalize( design, raw ), ( next ) => editPost( { meta: { [ design.metaKey ]: next } } ) ];
}

/** Mirrors LayoutDesign::sanitize_custom_css() in PHP: its limits and its messages. */
function customCssError( css: string, maxBytes: number ): string | null {
	if ( new TextEncoder().encode( css ).length > maxBytes ) {
		/* translators: %d: size limit in KB */
		return sprintf( __( 'Custom CSS must be under %d KB.', 'yaycheckout' ), Math.floor( maxBytes / 1000 ) );
	}

	if ( css.includes( '\0' ) || /<\/style|<script/i.test( css ) ) {
		return __( 'Custom CSS contains disallowed markup.', 'yaycheckout' );
	}

	return null;
}

type Swatch = { name?: string; slug: string; color?: string; gradient?: string };
type ColourOrigin = { name: string; slug: string; colors?: Swatch[]; gradients?: Swatch[] };
type PresetKind = 'color' | 'gradient';

/**
 * The custom property WordPress prints for a theme preset. Its name is the slug kebab-cased the way
 * getColorClassName() does, which is how core names `--wp--preset--*` properties.
 */
function presetVar( kind: PresetKind, slug: string ): string {
	const kebab = ( getColorClassName( 'x', slug ) ?? '' ).slice( 'has-'.length, -'-x'.length );

	return `var(--wp--preset--${ kind }--${ kebab })`;
}

/** The theme preset a stored `var(--wp--preset--…)` names, with its value for the indicator; undefined otherwise. */
function presetOf( value: string, kind: PresetKind, origins: ColourOrigin[] | undefined ): Swatch | undefined {
	const key = 'color' === kind ? 'colors' : 'gradients';

	return ( origins ?? [] )
		.flatMap( ( origin ) => origin[ key ] ?? [] )
		.find( ( swatch ) => presetVar( kind, swatch.slug ) === value );
}

/** Mirrors LayoutDesign::DESIGN_PRESET_PREFIX in PHP: the palette presets that offer the colour tokens to blocks. */
const DESIGN_PRESET_PREFIX = 'yaycheckout-';

/** The context core labels the user's palette ("Custom") with, in every colour picker. */
const CUSTOM_ORIGIN_CONTEXT = 'Indicates this palette is created by the user.';

/** The merchant's own palette (Global Styles), at a block's level when it sets one. */
function merchantPalette( blockName?: string ): Swatch[] {
	const features = select( blockEditorStore ).getSettings().__experimentalFeatures;

	return (
		( blockName ? features?.blocks?.[ blockName ]?.color?.palette?.custom : undefined ) ??
		features?.color?.palette?.custom ??
		[]
	);
}

function isLayoutEditor(): boolean {
	return select( editorStore ).getCurrentPostType() === editorData.postType;
}

/**
 * The colour tokens as swatches in every colour picker of the layout editor, as the presets design-presets.css
 * prints. A swatch's colour is a custom property no other swatch shares, so a pick matches its slug (or stores
 * that var()) and the block follows later Design changes. The editor's own document loads the same stylesheet
 * and design, so the swatches show the design's colours.
 *
 * Core groups a picker's swatches by origin only (Theme, Default, Custom), so they go first in the user's origin,
 * which core labels Custom and this labels Checkout. A merchant with their own Global Styles palette keeps the
 * Custom label, since their colours follow in the same group.
 *
 * Added here, not in PHP: the post editor rebuilds __experimentalFeatures from the theme's global styles over REST,
 * which drops a block_editor_settings_all palette. The filter's value replaces core's, so it reads the palette the
 * way core would (a block's own, then the root's), less a block attribute's `settings`.
 */
function addDesignPalette( design: EditorDesign ) {
	const swatches: Swatch[] = Object.keys( design.schema.colours ).map( ( token ) => {
		const slug = DESIGN_PRESET_PREFIX + token;

		return {
			/* translators: %s: a design colour, such as Primary */
			name: sprintf( __( 'Design: %s', 'yaycheckout' ), TOKEN_LABELS[ token ] ?? token ),
			slug,
			// Not the preset's own var(): the editor resolves a colour naming a preset through it, so it would never return.
			color: `var(--yaycheckout-design-${ token })`,
		};
	} );
	const groupLabel = __( 'Checkout', 'yaycheckout' );

	addFilter(
		'blockEditor.useSetting.before',
		'yaycheckout/design-palette',
		( value: unknown, path: string, _clientId: string | null, blockName?: string ) => {
			if ( 'color.palette.custom' !== path || ! isLayoutEditor() ) {
				return value;
			}

			return [ ...swatches, ...merchantPalette( blockName ) ];
		}
	);

	addFilter(
		'i18n.gettext_with_context_default',
		'yaycheckout/design-palette-label',
		( translation: string, text: string, context: string ) =>
			'Custom' === text && CUSTOM_ORIGIN_CONTEXT === context && isLayoutEditor() && ! merchantPalette().length
				? groupLabel
				: translation
	);
}

/** The palettes without the design's own presets: each reads a colour token, so a token naming one would read itself. */
function withoutDesignPresets( origins: ColourOrigin[] | undefined ): ColourOrigin[] | undefined {
	return origins
		?.map( ( origin ) => ( {
			...origin,
			colors: origin.colors?.filter( ( swatch ) => ! swatch.slug.startsWith( DESIGN_PRESET_PREFIX ) ),
		} ) )
		.filter( ( origin ) => origin.gradients?.length || origin.colors?.length );
}

function isColourRule( rule: DesignRule ): boolean {
	return 'color' === rule.type || 'fill' === rule.type;
}

/**
 * A section's colour tokens as the block editor's colour items, in a ToolsPanel styled like a block's Color panel.
 * A theme swatch is stored as its preset's custom property, so the layout follows later palette changes; a
 * custom pick is stored as written. Clear, or clicking the selected swatch again, unsets the colour (''), which
 * prints nothing, so the theme's colour shows. A fill (the background) also takes gradients.
 */
function ColourSettings( {
	label,
	panelId,
	rules,
	values,
	onChange,
}: {
	label: string;
	/** One per panel, so each panel's colour items register with its own ToolsPanel. */
	panelId: string;
	rules: Record< string, DesignRule >;
	values: SectionTokens;
	onChange: ( next: SectionTokens ) => void;
} ) {
	const allPalettes = useMultipleOriginColorsAndGradients();
	const palettes = { ...allPalettes, colors: withoutDesignPresets( allPalettes.colors ) };
	// The control calls back twice for one pick (the new value, then a clear of the other kind), so each call
	// must see the value the one before it left, not the value this render started with.
	const latest = useRef( values );
	latest.current = values;

	const change = ( token: string, kind: PresetKind, next: string | undefined, slug: string | undefined ) => {
		const current = String( latest.current[ token ] ?? '' );
		let value: string;

		if ( next ) {
			value = slug ? presetVar( kind, slug ) : next;
		} else {
			// No value: either the selected swatch was clicked again, or the control is clearing the other kind
			// after a pick. Only the first unsets, and only when the token holds this kind.
			const holdsKind = 'gradient' === kind ? isGradient( current ) : '' !== current && ! isGradient( current );
			if ( ! holdsKind ) {
				return;
			}
			value = '';
		}

		latest.current = { ...latest.current, [ token ]: value };
		onChange( latest.current );
	};

	const tokens = Object.entries( rules ).filter( ( [ , rule ] ) => isColourRule( rule ) );

	if ( ! tokens.length ) {
		return null;
	}

	const settings = tokens.map( ( [ token, rule ] ) => {
		const value = String( values[ token ] ?? '' );
		const isFill = 'fill' === rule.type;
		const gradient = isFill && isGradient( value );
		const colorPreset = gradient ? undefined : presetOf( value, 'color', palettes.colors );
		const gradientPreset = gradient ? presetOf( value, 'gradient', palettes.gradients ) : undefined;

		return {
			label: TOKEN_LABELS[ token ] ?? token,
			colorValue: gradient || '' === value ? undefined : colorPreset?.color ?? value,
			colorSlug: colorPreset?.slug,
			onColorChange: ( next?: string, slug?: string ) => change( token, 'color', next, slug ),
			...( isFill
				? {
						gradientValue: gradient ? gradientPreset?.gradient ?? value : undefined,
						gradientSlug: gradientPreset?.slug,
						onGradientChange: ( next?: string, slug?: string ) => change( token, 'gradient', next, slug ),
				  }
				: {} ),
			// Clear empties the token (''), so the theme's colour shows.
			clearable: true,
			isShownByDefault: true,
		};
	} );

	return (
		<ToolsPanel
			label={ label }
			panelId={ panelId }
			hasInnerWrapper
			// The panelId as a class too: the editor tour finds the Colours panel by it.
			className={ `color-block-support-panel ${ panelId }` }
			__experimentalFirstVisibleItemClass="first"
			__experimentalLastVisibleItemClass="last"
			resetAll={ () =>
				onChange( {
					...values,
					...Object.fromEntries( tokens.map( ( [ token, rule ] ) => [ token, rule.default ] ) ),
				} )
			}
		>
			<div className="color-block-support-panel__inner-wrapper">
				<ColorGradientSettingsDropdown
					__experimentalIsRenderedInSidebar
					panelId={ panelId }
					settings={ settings }
					colors={ palettes.colors }
					gradients={ palettes.gradients }
					disableCustomColors={ palettes.disableCustomColors }
					disableCustomGradients={ palettes.disableCustomGradients }
					enableAlpha={ false }
				/>
			</div>
		</ToolsPanel>
	);
}

function TokenControl( {
	design,
	token,
	rule,
	value,
	help,
	onChange,
}: {
	design: EditorDesign;
	token: string;
	rule: DesignRule;
	value: string | number | null;
	help?: string;
	onChange: ( next: string | number | null ) => void;
} ) {
	const label = TOKEN_LABELS[ token ] ?? token;

	switch ( rule.type ) {
		case 'int':
			return (
				<RangeControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ label }
					min={ rule.min }
					max={ rule.max }
					// Reset empties the token (null): the theme's value shows, or for the base size DEFAULT_BASE_SIZE.
					allowReset
					value={ 'number' === typeof value ? value : ( 'base_size' === token ? DEFAULT_BASE_SIZE : undefined ) }
					afterIcon={ <span>px</span> }
					onChange={ ( next ) => onChange( 'number' === typeof next ? next : null ) }
				/>
			);

		case 'enum':
			return (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ label }
					help={ help }
					value={ String( value ?? '' ) }
					options={ rule.enum.map( ( option ) => ( {
						value: option,
						label:
							'font_family' === token
								? design.fontFamilies[ option ] || ENUM_LABELS[ '' ]
								: ( 'focus_style' === token ? FOCUS_STYLE_LABELS[ option ] : undefined ) ??
								  ENUM_LABELS[ option ] ??
								  option,
					} ) ) }
					onChange={ ( next: string ) => onChange( next ) }
				/>
			);
	}

	return null;
}

function SectionPanel( {
	design,
	section,
	values,
	fontHelp,
	onChange,
}: {
	design: EditorDesign;
	section: Section;
	values: SectionTokens;
	/** Under the Font control: the install in progress, or why it failed. */
	fontHelp?: string;
	onChange: ( next: SectionTokens ) => void;
} ) {
	// A token that no longer shows on the checkout: an underline input has no radius, a hidden label no colour.
	const rules = Object.fromEntries(
		Object.entries( design.schema[ section ] ).filter(
			( [ token ] ) =>
				! ( 'radius' === token && 'underline' === values.style ) &&
				! ( 'label_color' === token && 'hidden' === values.label )
		)
	);
	const colours = (
		<ColourSettings
			label={ COLOUR_PANELS[ section ] }
			panelId={ `${ SIDEBAR }-${ section }` }
			rules={ rules }
			values={ values }
			onChange={ onChange }
		/>
	);
	const others = Object.entries( rules ).filter( ( [ , rule ] ) => ! isColourRule( rule ) );

	if ( ! others.length ) {
		return colours;
	}

	// The choices that shape the part come first, its colours after.
	return (
		<PanelBody title={ SECTIONS[ section ] } initialOpen={ false }>
			<Flex direction="column" align="stretch" gap={ 4 }>
				{ others.map( ( [ token, rule ] ) => (
					<TokenControl
						key={ token }
						design={ design }
						token={ token }
						rule={ rule }
						value={ values[ token ] }
						help={ 'font_family' === token ? fontHelp : undefined }
						onChange={ ( next ) => onChange( { ...values, [ token ]: next } ) }
					/>
				) ) }
				{ colours }
			</Flex>
		</PanelBody>
	);
}

/**
 * The picked font's `@font-face` rules for the canvas (FontRestController): '' for the theme's font or one the
 * theme loads, the uploaded files once the font is uploaded, and Google's files as a preview until then. The
 * font uploads when the layout is saved (Engine\DesignFontInstall), so the rules are asked for again after
 * every save. The CSS is null until the answer, so the faces already printed stay meanwhile.
 */
function useDesignFont( design: EditorDesign, slug: string ): { css: string | null; help?: string } {
	const { savedSlug, isDirty, isSaving, saveFailed } = useSelect(
		( selectStore ) => {
			const editor = selectStore( editorStore );
			const meta = editor.getCurrentPostAttribute( 'meta' ) as Record< string, unknown > | undefined;
			const saved = meta?.[ design.metaKey ] as LayoutDesignMeta | undefined;

			return {
				savedSlug: String( saved?.typography?.font_family ?? '' ),
				isDirty: editor.isEditedPostDirty(),
				isSaving: editor.isSavingPost(),
				saveFailed: editor.didPostSaveRequestFail(),
			};
		},
		[ design.metaKey ]
	);
	const [ saves, setSaves ] = useState( 0 );
	const wasSaving = useRef( isSaving );
	const [ state, setState ] = useState< { slug: string; css: string | null; uploaded: boolean; error?: string } >( {
		slug,
		css: null,
		uploaded: true,
	} );

	useEffect( () => {
		if ( wasSaving.current && ! isSaving && ! saveFailed ) {
			setSaves( ( count ) => count + 1 );
		}
		wasSaving.current = isSaving;
	}, [ isSaving, saveFailed ] );

	useEffect( () => {
		let cancelled = false;

		// The theme's font, or one the device has: no faces to print.
		if ( '' === slug || design.fontStacks[ slug ] ) {
			setState( { slug, css: '', uploaded: true } );
			return;
		}

		// After a save the same font keeps its faces until the answer, so the canvas doesn't flicker.
		setState( ( previous ) => ( previous.slug === slug ? previous : { slug, css: null, uploaded: true } ) );

		(
			apiFetch( { path: `/yaycheckout/v1/fonts/${ slug }` } ) as Promise< { uploaded: boolean; css: string } >
		).then(
			( response ) => {
				if ( ! cancelled ) {
					setState( { slug, css: response.css, uploaded: response.uploaded } );
				}
			},
			async ( error: unknown ) => {
				const message = await errorMessage( error, __( 'The font could not be loaded.', 'yaycheckout' ) );

				if ( ! cancelled ) {
					setState( { slug, css: '', uploaded: false, error: message } );
				}
			}
		);

		return () => {
			cancelled = true;
		};
	}, [ slug, saves, design.fontStacks ] );

	if ( '' === slug || design.fontStacks[ slug ] ) {
		return { css: '' };
	}

	if ( state.slug !== slug || null === state.css ) {
		return { css: null };
	}

	if ( state.error ) {
		return { css: state.css, help: state.error };
	}

	if ( state.uploaded ) {
		return { css: state.css };
	}

	// Saved here, yet still not uploaded: the save's upload failed. The reason is in WooCommerce → Status → Logs.
	if ( saves > 0 && slug === savedSlug && ! isDirty ) {
		return {
			css: state.css,
			help: __( "Couldn't upload this font. It shows only where the theme provides it.", 'yaycheckout' ),
		};
	}

	return {
		css: state.css,
		help: __( 'Previewed from Google Fonts. Uploads to your site when you save.', 'yaycheckout' ),
	};
}

/** How long the sidebar waits after the last change before writing it to the post. */
const COMMIT_DELAY = 250;

function LayoutDesignSidebar( { design }: { design: EditorDesign } ) {
	const [ stored, store ] = useLayoutDesign( design );
	// Edits live here while the merchant drags or types, and reach the post once they pause: every editPost
	// re-renders the whole editor and adds an undo step, which made a dragged colour picker crawl.
	const [ draft, setDraft ] = useState< LayoutDesignMeta | null >( null );
	const current = draft ?? stored;
	const pending = useRef< { next: LayoutDesignMeta | null; timer?: number } >( { next: null } );
	const storeRef = useRef( store );
	storeRef.current = store;

	const flush = () => {
		window.clearTimeout( pending.current.timer );
		if ( pending.current.next ) {
			storeRef.current( pending.current.next );
			pending.current.next = null;
			setDraft( null );
		}
	};
	const flushRef = useRef( flush );
	flushRef.current = flush;

	const setCurrent = ( next: LayoutDesignMeta ) => {
		setDraft( next );
		pending.current.next = next;
		window.clearTimeout( pending.current.timer );
		pending.current.timer = window.setTimeout( () => flushRef.current(), COMMIT_DELAY );
	};

	// Leaving the layout editor must not drop a pending edit.
	useEffect( () => () => flushRef.current(), [] );
	const cssError = customCssError( current.custom_css, design.customCssMaxBytes );
	const { lockPostSaving, unlockPostSaving } = useDispatch( editorStore );

	const font = useDesignFont( design, String( current.typography.font_family ?? '' ) );

	useCanvasStyle( designCss( current, design ), design.styleId );
	useCanvasStyle( font.css, design.fontStyleId );

	// The server would skip this CSS on print without saying so, so it is never saved.
	useEffect( () => {
		if ( cssError ) {
			lockPostSaving( CSS_LOCK );
		} else {
			unlockPostSaving( CSS_LOCK );
		}
	}, [ cssError ] );

	return (
		<>
			<PluginSidebarMoreMenuItem target={ SIDEBAR } icon="admin-appearance">
				{ __( 'Layout design', 'yaycheckout' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar name={ SIDEBAR } title={ __( 'Layout design', 'yaycheckout' ) } icon="admin-appearance">
				<PanelBody>
					<p className="yaycheckout-layout-design__help">
						{ __(
							"This layout's design. An empty value follows your theme.",
							'yaycheckout'
						) }
					</p>
				</PanelBody>
				{ ( Object.keys( SECTIONS ) as Section[] ).map( ( section ) => (
					<SectionPanel
						key={ section }
						design={ design }
						section={ section }
						values={ current[ section ] }
						fontHelp={ 'typography' === section ? font.help : undefined }
						onChange={ ( next ) => setCurrent( { ...current, [ section ]: next } ) }
					/>
				) ) }
				<PanelBody title={ __( 'Custom CSS', 'yaycheckout' ) } initialOpen={ '' !== current.custom_css }>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Custom CSS', 'yaycheckout' ) }
						hideLabelFromVision
						className={ `yaycheckout-layout-design__css${ cssError ? ' is-invalid' : '' }` }
						rows={ 10 }
						value={ current.custom_css }
						help={
							cssError ??
							__(
								'Printed on the checkout after the site-wide custom CSS (Settings → Advanced). Not shown in the editor.',
								'yaycheckout'
							)
						}
						onChange={ ( next: string ) => setCurrent( { ...current, custom_css: next } ) }
					/>
				</PanelBody>
			</PluginSidebar>
		</>
	);
}

function LayoutDesign() {
	const isLayout = useIsLayoutEditor();

	if ( ! isLayout || ! editorData.design ) {
		return null;
	}

	return <LayoutDesignSidebar design={ editorData.design } />;
}

registerPlugin( SIDEBAR, {
	render: LayoutDesign,
} );

if ( editorData.design ) {
	addDesignPalette( editorData.design );
}
