import {
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	Flex,
	PanelBody,
	RangeControl,
	SelectControl,
	TextareaControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanel as ToolsPanel,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginSidebar, PluginSidebarMoreMenuItem, store as editorStore } from '@wordpress/editor';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { designCss, isGradient } from '../shared/design-css';
import { editorData, type DesignRule, type DesignTokens, type EditorDesign } from '../shared/editor-data';
import './layout-design.css';

const SIDEBAR = 'yaycheckout-layout-design';
const CSS_LOCK = 'yaycheckout-layout-design-css';

type SectionTokens = Record< string, string | number | boolean >;

/** Layout::META_DESIGN as LayoutDesign::to_meta() shapes it: null follows the global design. */
export type LayoutDesignMeta = {
	colours: SectionTokens | null;
	typography: SectionTokens | null;
	inputs: SectionTokens | null;
	custom_css: string;
};

type Section = 'colours' | 'typography' | 'inputs';
type Mode = 'global' | 'custom';

const SECTIONS: Record< Section, string > = {
	colours: __( 'Colours', 'yaycheckout' ),
	typography: __( 'Typography', 'yaycheckout' ),
	inputs: __( 'Inputs', 'yaycheckout' ),
};

const TOKEN_LABELS: Record< string, string > = {
	primary: __( 'Primary', 'yaycheckout' ),
	text: __( 'Text', 'yaycheckout' ),
	background: __( 'Background', 'yaycheckout' ),
	border: __( 'Border', 'yaycheckout' ),
	font_family: __( 'Font', 'yaycheckout' ),
	base_size: __( 'Base size', 'yaycheckout' ),
	style: __( 'Style', 'yaycheckout' ),
	size: __( 'Size', 'yaycheckout' ),
	radius: __( 'Radius', 'yaycheckout' ),
};

const ENUM_LABELS: Record< string, string > = {
	boxed: __( 'Boxed', 'yaycheckout' ),
	underline: __( 'Underline', 'yaycheckout' ),
	small: __( 'Small', 'yaycheckout' ),
	medium: __( 'Medium', 'yaycheckout' ),
	large: __( 'Large', 'yaycheckout' ),
};

const NONE: LayoutDesignMeta = { colours: null, typography: null, inputs: null, custom_css: '' };

function isTokens( value: unknown ): value is SectionTokens {
	return null !== value && 'object' === typeof value && ! Array.isArray( value );
}

/** The meta as the editor holds it, with every key present. */
function normalize( raw: unknown ): LayoutDesignMeta {
	if ( ! isTokens( raw ) ) {
		return NONE;
	}

	return {
		colours: isTokens( raw.colours ) ? raw.colours : null,
		typography: isTokens( raw.typography ) ? raw.typography : null,
		inputs: isTokens( raw.inputs ) ? raw.inputs : null,
		custom_css: 'string' === typeof raw.custom_css ? raw.custom_css : '',
	};
}

/** The edited override and its setter; writing marks the post dirty, and Save stores it with the markup. */
function useLayoutDesign( design: EditorDesign ): [ LayoutDesignMeta, ( next: LayoutDesignMeta ) => void ] {
	const raw = useSelect(
		( selectStore ) =>
			( selectStore( editorStore ).getEditedPostAttribute( 'meta' ) as Record< string, unknown > | undefined )?.[
				design.metaKey
			],
		[ design.metaKey ]
	);
	const { editPost } = useDispatch( editorStore );

	return [ normalize( raw ), ( next ) => editPost( { meta: { [ design.metaKey ]: next } } ) ];
}

/** The global tokens with each overridden section swapped in, as LayoutDesign::tokens_over() does in PHP. */
function effectiveTokens( tokens: DesignTokens, override: LayoutDesignMeta ): DesignTokens {
	const effective = { ...tokens };

	( Object.keys( SECTIONS ) as Section[] ).forEach( ( section ) => {
		const values = override[ section ];
		if ( values ) {
			effective[ section ] = values;
		}
	} );

	return effective;
}

/** Mirrors Design::sanitize_custom_css() in PHP: its limits and its messages. */
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

function modeOf( values: SectionTokens | null ): Mode {
	return values ? 'custom' : 'global';
}

/** Where a section starts when it leaves Global design: the global values while that section is on, else the defaults. */
function seed( design: EditorDesign, section: Section ): SectionTokens {
	if ( design.tokens[ section ]?.enabled ) {
		return { ...design.tokens[ section ] };
	}

	return Object.fromEntries(
		Object.entries( design.schema[ section ] ).map( ( [ key, rule ] ) => [ key, rule.default ] )
	);
}

type ColourOrigin = { name: string; slug: string; colors?: { color: string }[]; gradients?: { gradient: string }[] };

/** Each palette origin with only the swatches `accepts` keeps; origins left empty are dropped. */
function keepSwatches< T >(
	origins: ColourOrigin[] | undefined,
	key: 'colors' | 'gradients',
	accepts: ( item: T ) => boolean
): ColourOrigin[] {
	return ( origins ?? [] )
		.map( ( origin ) => ( { ...origin, [ key ]: ( ( origin[ key ] ?? [] ) as T[] ).filter( accepts ) } ) )
		.filter( ( origin ) => ( origin[ key ] as T[] ).length > 0 );
}

function isColourRule( rule: DesignRule ): boolean {
	return 'hex_color' === rule.type || 'fill' === rule.type;
}

/**
 * A section's colour tokens as the block editor's colour items, in a ToolsPanel styled like a block's Color panel.
 * Palettes keep only swatches the meta's schema accepts, so a pick never fails Save; a fill also gets gradients.
 */
function ColourSettings( {
	design,
	rules,
	values,
	onChange,
}: {
	design: EditorDesign;
	rules: Record< string, DesignRule >;
	values: SectionTokens;
	onChange: ( next: SectionTokens ) => void;
} ) {
	const palettes = useMultipleOriginColorsAndGradients();
	// Stable between edits, so the open picker's palette doesn't re-render on every change.
	const colors = useMemo( () => {
		const hex = new RegExp( design.hexPattern );

		return keepSwatches< { color: string } >( palettes.colors, 'colors', ( { color } ) => hex.test( color ) );
	}, [ palettes.colors, design.hexPattern ] );
	const gradients = useMemo( () => {
		const gradient = new RegExp( design.gradientPattern );

		return keepSwatches< { gradient: string } >(
			palettes.gradients,
			'gradients',
			( item ) => item.gradient.length <= design.gradientMaxLength && gradient.test( item.gradient )
		);
	}, [ palettes.gradients, design.gradientPattern, design.gradientMaxLength ] );

	const tokens = Object.entries( rules ).filter( ( [ , rule ] ) => isColourRule( rule ) );
	// Picking a colour on a control that also takes gradients calls onGradientChange() with nothing, and the
	// reverse, to clear the other kind; a Custom section always holds every token, so an empty call is ignored.
	const set = ( token: string, next: string | undefined ) => {
		if ( next ) {
			onChange( { ...values, [ token ]: next } );
		}
	};

	const settings = tokens.map( ( [ token, rule ] ) => {
		const value = String( values[ token ] );
		const isFill = 'fill' === rule.type;

		return {
			label: TOKEN_LABELS[ token ] ?? token,
			colorValue: isFill && isGradient( value ) ? undefined : value,
			onColorChange: ( next?: string ) => set( token, next ),
			...( isFill
				? {
						gradientValue: isGradient( value ) ? value : undefined,
						onGradientChange: ( next?: string ) => set( token, next ),
				  }
				: {} ),
			clearable: false,
			isShownByDefault: true,
		};
	} );

	return (
		<ToolsPanel
			label={ __( 'Custom colours', 'yaycheckout' ) }
			panelId={ SIDEBAR }
			hasInnerWrapper
			className="color-block-support-panel"
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
					panelId={ SIDEBAR }
					settings={ settings }
					colors={ colors }
					gradients={ gradients }
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
	onChange,
}: {
	design: EditorDesign;
	token: string;
	rule: DesignRule;
	value: string | number | boolean;
	onChange: ( next: string | number ) => void;
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
					value={ Number( value ) }
					afterIcon={ <span>px</span> }
					onChange={ ( next ) => onChange( 'number' === typeof next ? next : rule.default ) }
				/>
			);

		case 'enum':
			return (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ label }
					value={ String( value ) }
					options={ rule.enum.map( ( option ) => ( {
						value: option,
						label:
							'font_family' === token
								? design.fontFamilies[ option ] || __( 'Theme default', 'yaycheckout' )
								: ENUM_LABELS[ option ] ?? option,
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
	onChange,
}: {
	design: EditorDesign;
	section: Section;
	values: SectionTokens | null;
	onChange: ( next: SectionTokens | null ) => void;
} ) {
	const mode = modeOf( values );

	const setMode = ( next: Mode ) => {
		if ( 'global' === next ) {
			onChange( null );
			return;
		}

		onChange( { ...( values ?? seed( design, section ) ), enabled: true } );
	};

	return (
		<PanelBody title={ SECTIONS[ section ] } initialOpen={ 'global' !== mode }>
			<Flex direction="column" align="stretch" gap={ 4 }>
				<ToggleGroupControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					isBlock
					hideLabelFromVision
					label={ SECTIONS[ section ] }
					value={ mode }
					onChange={ ( next: string | number | undefined ) => setMode( next as Mode ) }
				>
					<ToggleGroupControlOption value="global" label={ __( 'Global design', 'yaycheckout' ) } />
					<ToggleGroupControlOption value="custom" label={ __( 'Custom', 'yaycheckout' ) } />
				</ToggleGroupControl>
				{ 'custom' === mode && values && 'colours' === section && (
					<ColourSettings
						design={ design }
						rules={ design.schema[ section ] }
						values={ values }
						onChange={ onChange }
					/>
				) }
				{ 'custom' === mode &&
					values &&
					Object.entries( design.schema[ section ] ).map( ( [ token, rule ] ) => {
						// Enabled is the mode above, colours are in ColourSettings, and an underline input has no radius.
						if (
							'enabled' === token ||
							isColourRule( rule ) ||
							( 'radius' === token && 'underline' === values.style )
						) {
							return null;
						}

						return (
							<TokenControl
								key={ token }
								design={ design }
								token={ token }
								rule={ rule }
								value={ values[ token ] }
								onChange={ ( next ) => onChange( { ...values, [ token ]: next } ) }
							/>
						);
					} ) }
			</Flex>
		</PanelBody>
	);
}

/** The canvas iframe's documents. */
function canvasFrames(): Document[] {
	return Array.from( document.querySelectorAll< HTMLIFrameElement >( 'iframe[name="editor-canvas"]' ) )
		.map( ( frame ) => frame.contentDocument )
		.filter( ( doc ): doc is Document => !! doc );
}

/**
 * Keeps the printed design style in the canvas equal to `css`. The canvas iframe is rebuilt on a device
 * preview switch or after the code editor, and comes back with the saved design, so it is watched.
 */
function useCanvasDesign( css: string, styleId: string ) {
	const latest = useRef( css );
	latest.current = css;

	const apply = () => {
		const frames = canvasFrames();

		// The editor's own document holds the canvas when it isn't iframed. WordPress prints no style for an
		// empty design, so a canvas without one gets its own, after the base stylesheet.
		( frames.length ? frames : [ document ] ).forEach( ( doc ) => {
			if ( doc.head && ! doc.getElementById( styleId ) ) {
				const created = doc.createElement( 'style' );
				created.id = styleId;
				doc.head.appendChild( created );
			}
		} );

		[ document, ...frames ].forEach( ( doc ) => {
			const style = doc.getElementById( styleId );
			if ( style && style.textContent !== latest.current ) {
				style.textContent = latest.current;
			}
		} );
	};

	useEffect( apply, [ css ] );

	useEffect( () => {
		// Only each canvas head, where the style is printed, so editing blocks doesn't wake it.
		const observer = new MutationObserver( apply );
		const watched = new WeakSet< Document >();

		const watchFrames = () => {
			canvasFrames().forEach( ( doc ) => {
				if ( doc.head && ! watched.has( doc ) ) {
					watched.add( doc );
					observer.observe( doc.head, { childList: true } );
				}
			} );
			apply();
		};

		// A rebuilt canvas iframe loads a new document; load doesn't bubble, so it is caught on the way down.
		document.addEventListener( 'load', watchFrames, true );
		watchFrames();

		return () => {
			observer.disconnect();
			document.removeEventListener( 'load', watchFrames, true );
		};
	}, [] );
}

/** How long the sidebar waits after the last change before writing it to the post. */
const COMMIT_DELAY = 250;

function LayoutDesignSidebar( { design }: { design: EditorDesign } ) {
	const [ stored, store ] = useLayoutDesign( design );
	// Edits live here while the merchant drags or types, and reach the post once they pause: every editPost
	// re-renders the whole editor and adds an undo step, which made a dragged colour picker crawl.
	const [ draft, setDraft ] = useState< LayoutDesignMeta | null >( null );
	const override = draft ?? stored;
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

	const setOverride = ( next: LayoutDesignMeta ) => {
		setDraft( next );
		pending.current.next = next;
		window.clearTimeout( pending.current.timer );
		pending.current.timer = window.setTimeout( () => flushRef.current(), COMMIT_DELAY );
	};

	// Leaving the layout editor must not drop a pending edit.
	useEffect( () => () => flushRef.current(), [] );
	const cssError = customCssError( override.custom_css, design.customCssMaxBytes );
	const { lockPostSaving, unlockPostSaving } = useDispatch( editorStore );

	useCanvasDesign( designCss( effectiveTokens( design.tokens, override ), design.fontFamilies ), design.styleId );

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
							'Override the global design for this layout only. Global design follows the Design page.',
							'yaycheckout'
						) }
					</p>
				</PanelBody>
				{ ( Object.keys( SECTIONS ) as Section[] ).map( ( section ) => (
					<SectionPanel
						key={ section }
						design={ design }
						section={ section }
						values={ override[ section ] }
						onChange={ ( next ) => setOverride( { ...override, [ section ]: next } ) }
					/>
				) ) }
				<PanelBody title={ __( 'Custom CSS', 'yaycheckout' ) } initialOpen={ '' !== override.custom_css }>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Custom CSS', 'yaycheckout' ) }
						hideLabelFromVision
						className={ `yaycheckout-layout-design__css${ cssError ? ' is-invalid' : '' }` }
						rows={ 10 }
						value={ override.custom_css }
						help={
							cssError ??
							__( 'Printed on the checkout after the global custom CSS. Not shown in the editor.', 'yaycheckout' )
						}
						onChange={ ( next: string ) => setOverride( { ...override, custom_css: next } ) }
					/>
				</PanelBody>
			</PluginSidebar>
		</>
	);
}

function LayoutDesign() {
	const isLayout = useSelect(
		( selectStore ) => selectStore( editorStore ).getCurrentPostType() === editorData.postType,
		[]
	);

	if ( ! isLayout || ! editorData.design ) {
		return null;
	}

	return <LayoutDesignSidebar design={ editorData.design } />;
}

registerPlugin( SIDEBAR, {
	render: LayoutDesign,
} );
