import {
	Button,
	ColorIndicator,
	ColorPicker,
	Dashicon,
	Dropdown,
	Flex,
	RangeControl,
	SelectControl,
	TextareaControl,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginSidebar, store as editorStore } from '@wordpress/editor';
import { useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { ConfirmModal } from '../shared/confirm-modal';
import { designVars } from '../shared/design-vars';
import { editorData, type DesignTokens, type TokenRule, type TokenValue } from '../shared/editor-data';
import { useLiveDesignStyle } from './use-live-design-style';
import '../shared/editor-panel.css';
import './layout-design.css';

/** Layout::META_DESIGN in PHP. */
const META_KEY = '_yaycheckout_design';
const CSS_ROW = 'custom_css';

/** The meta's shape: only the sections and CSS switched to Custom. */
type Layer = { tokens?: DesignTokens; custom_css?: string };
type Screen = string | null;

const DESIGN = editorData.design;

const SECTION_LABELS: Record< string, string > = {
	colours: __( 'Colours', 'yaycheckout' ),
	typography: __( 'Typography', 'yaycheckout' ),
	inputs: __( 'Inputs', 'yaycheckout' ),
	[ CSS_ROW ]: __( 'Custom CSS', 'yaycheckout' ),
};

const SECTION_ICONS: Record< string, string > = {
	colours: 'art',
	typography: 'editor-textcolor',
	inputs: 'feedback',
	[ CSS_ROW ]: 'editor-code',
};

const KEY_LABELS: Record< string, string > = {
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

/** Core's `styles` icon; @wordpress/icons is not a dependency of this package. */
const STYLES_ICON = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
		<path
			fillRule="evenodd"
			clipRule="evenodd"
			d="M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-1.5 0a6.5 6.5 0 0 1-6.5 6.5v-13a6.5 6.5 0 0 1 6.5 6.5Z"
		/>
	</svg>
);

/** Mirrors Design::is_valid() in PHP; the rules themselves come from Design::schema() through editorData. */
function isValid( value: unknown, rule: TokenRule ): boolean {
	switch ( rule.type ) {
		case 'bool':
			return typeof value === 'boolean';
		case 'int':
			return Number.isInteger( value ) && ( value as number ) >= ( rule.min ?? 0 ) && ( value as number ) <= ( rule.max ?? 0 );
		case 'enum':
			return ( rule.enum ?? [] ).includes( value as string );
		case 'hex_color':
			return typeof value === 'string' && /^#[0-9A-Fa-f]{6}$/.test( value );
	}
	return false;
}

/**
 * The stored meta as Design::project_layer() reads it: a section counts only when every key is valid,
 * so a stale one shows as Global and the next save omits it. REST serves no meta as `[]`.
 */
function readLayer( raw: unknown ): Layer {
	if ( ! DESIGN || ! raw || typeof raw !== 'object' || Array.isArray( raw ) ) {
		return {};
	}
	const { tokens, custom_css: css } = raw as Layer;
	const layer: Layer = {};

	for ( const [ section, rules ] of Object.entries( DESIGN.schema ) ) {
		const values = tokens?.[ section ];
		if ( values && Object.entries( rules ).every( ( [ key, rule ] ) => isValid( values[ key ], rule ) ) ) {
			layer.tokens = { ...layer.tokens, [ section ]: values };
		}
	}
	if ( typeof css === 'string' && css !== '' ) {
		layer.custom_css = css;
	}

	return layer;
}

function displayValue( key: string, value: TokenValue ): string {
	if ( key === 'enabled' ) {
		return value ? __( 'On', 'yaycheckout' ) : __( 'Theme default', 'yaycheckout' );
	}
	if ( key === 'font_family' ) {
		return DESIGN?.font_families[ String( value ) ] || __( 'Theme default', 'yaycheckout' );
	}
	if ( typeof value === 'number' ) {
		return `${ value }px`;
	}
	return ENUM_LABELS[ String( value ) ] ?? String( value );
}

function cssLines( css: string ): string {
	const lines = css.trim() ? css.trim().split( '\n' ).length : 0;
	/* translators: %d: number of lines of custom CSS */
	return sprintf( _n( '%d line', '%d lines', lines, 'yaycheckout' ), lines );
}

/** One line per value the layer changes: "Label: old → global". */
function changes( section: string, layer: Layer, global: DesignTokens ): string[] {
	const values = layer.tokens?.[ section ] ?? {};

	return Object.keys( values )
		.filter( ( key ) => values[ key ] !== global[ section ][ key ] )
		.map( ( key ) => `${ KEY_LABELS[ key ] ?? SECTION_LABELS[ section ] }: ${ displayValue( key, values[ key ] ) } → ${ displayValue( key, global[ section ][ key ] ) }` );
}

function summary( section: string, tokens: DesignTokens ) {
	const values = tokens[ section ];
	if ( ! values.enabled ) {
		return __( 'Theme default', 'yaycheckout' );
	}
	if ( section === 'colours' ) {
		return (
			<Flex justify="flex-start" gap={ 1 }>
				{ [ 'primary', 'text', 'background', 'border' ].map( ( key ) => (
					<ColorIndicator key={ key } colorValue={ String( values[ key ] ) } />
				) ) }
			</Flex>
		);
	}
	return Object.keys( values )
		.filter( ( key ) => key !== 'enabled' )
		.map( ( key ) => displayValue( key, values[ key ] ) )
		.join( ' · ' );
}

function TokenControl( { name, rule, value, onChange }: { name: string; rule: TokenRule; value: TokenValue; onChange: ( value: TokenValue ) => void } ) {
	const label = KEY_LABELS[ name ] ?? name;

	if ( rule.type === 'hex_color' ) {
		return (
			<Dropdown
				popoverProps={ { placement: 'left-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button __next40pxDefaultSize className="yaycheckout-layout-design__colour" onClick={ onToggle } aria-expanded={ isOpen }>
						<ColorIndicator colorValue={ String( value ) } />
						<span>{ label }</span>
						<code>{ String( value ) }</code>
					</Button>
				) }
				renderContent={ () => <ColorPicker color={ String( value ) } onChange={ ( hex: string ) => onChange( hex.slice( 0, 7 ) ) } /> }
			/>
		);
	}
	if ( rule.type === 'int' ) {
		return (
			<RangeControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				min={ rule.min }
				max={ rule.max }
				value={ Number( value ) }
				onChange={ ( next ) => onChange( Math.round( next ?? Number( rule.default ) ) ) }
			/>
		);
	}
	if ( name === 'font_family' ) {
		return (
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				value={ String( value ) }
				options={ ( rule.enum ?? [] ).map( ( slug ) => ( {
					value: slug,
					label: DESIGN?.font_families[ slug ] || __( 'Theme default', 'yaycheckout' ),
				} ) ) }
				onChange={ onChange }
			/>
		);
	}
	return (
		<ToggleGroupControl __next40pxDefaultSize __nextHasNoMarginBottom isBlock label={ label } value={ String( value ) } onChange={ ( next ) => onChange( String( next ) ) }>
			{ ( rule.enum ?? [] ).map( ( option ) => (
				<ToggleGroupControlOption key={ option } value={ option } label={ ENUM_LABELS[ option ] ?? option } />
			) ) }
		</ToggleGroupControl>
	);
}

function ScreenHeader( { section, isUnsaved, onRevert, onBack }: { section: string; isUnsaved: boolean; onRevert: () => void; onBack: () => void } ) {
	return (
		<Flex justify="flex-start" gap={ 2 } className="yaycheckout-layout-design__header">
			<Button size="small" icon={ <Dashicon icon="arrow-left-alt2" /> } label={ __( 'Back', 'yaycheckout' ) } onClick={ onBack } />
			<h2>{ SECTION_LABELS[ section ] }</h2>
			{ isUnsaved && (
				<Button size="small" icon={ <Dashicon icon="image-rotate" /> } label={ __( 'Revert to saved', 'yaycheckout' ) } showTooltip onClick={ onRevert } />
			) }
		</Flex>
	);
}

/** CSS adds to global, so it has no Global/Custom switch: empty means none. */
function CssScreen( { layer, saved, onChange, onBack }: { layer: Layer; saved: Layer; onChange: ( layer: Layer ) => void; onBack: () => void } ) {
	const setCss = ( css: string ) => {
		const { custom_css: _css, ...rest } = layer;
		onChange( css === '' ? rest : { ...rest, custom_css: css } );
	};

	return (
		<div className="yaycheckout-layout-design">
			<ScreenHeader section={ CSS_ROW } isUnsaved={ layer.custom_css !== saved.custom_css } onRevert={ () => setCss( saved.custom_css ?? '' ) } onBack={ onBack } />
			<p className="yaycheckout-editor-help">
				{ __( 'Added after the global custom CSS. Shown on the storefront and in preview, not here.', 'yaycheckout' ) }
			</p>
			<TextareaControl __nextHasNoMarginBottom label={ SECTION_LABELS[ CSS_ROW ] } rows={ 10 } value={ layer.custom_css ?? '' } onChange={ setCss } />
		</div>
	);
}

function GroupScreen( {
	section,
	layer,
	saved,
	global,
	onChange,
	onBack,
}: {
	section: string;
	layer: Layer;
	/** The layer as last saved, for Revert to saved. */
	saved: Layer;
	global: DesignTokens;
	onChange: ( layer: Layer ) => void;
	onBack: () => void;
} ) {
	const [ isReverting, setReverting ] = useState( false );
	const current = layer.tokens?.[ section ];
	const savedValues = saved.tokens?.[ section ];
	const rules = DESIGN?.schema[ section ] ?? {};

	const setSection = ( values: Record< string, TokenValue > | undefined ) => {
		const { [ section ]: _old, ...rest } = layer.tokens ?? {};
		const tokens = values ? { ...rest, [ section ]: values } : rest;
		const { tokens: _tokens, ...withoutTokens } = layer;
		onChange( Object.keys( tokens ).length ? { ...withoutTokens, tokens } : withoutTokens );
	};
	const toggle = ( on: boolean ) => {
		if ( on ) {
			// Seeded from global and always on: a group at theme default globally starts printing global's stored values.
			return setSection( { ...global[ section ], enabled: true } );
		}
		return changes( section, layer, global ).length ? setReverting( true ) : setSection( undefined );
	};

	return (
		<div className="yaycheckout-layout-design">
			<ScreenHeader
				section={ section }
				// Both sides keep schema key order (readLayer, seeding and edits spread it), so the strings compare values.
				isUnsaved={ JSON.stringify( current ) !== JSON.stringify( savedValues ) }
				onRevert={ () => setSection( savedValues ) }
				onBack={ onBack }
			/>
			<ToggleControl __nextHasNoMarginBottom label={ __( 'Custom for this layout', 'yaycheckout' ) } checked={ !! current } onChange={ toggle } />
			{ current ? (
				<Flex direction="column" align="stretch" gap={ 4 }>
					{ Object.entries( rules )
						.filter( ( [ key ] ) => key !== 'enabled' )
						.map( ( [ key, rule ] ) => (
							// enabled: true too, so an imported section stored at theme default prints once edited.
							<TokenControl key={ key } name={ key } rule={ rule } value={ current[ key ] } onChange={ ( value ) => setSection( { ...current, [ key ]: value, enabled: true } ) } />
						) ) }
				</Flex>
			) : (
				<div className="yaycheckout-editor-help">
					{ __( 'Follows the Design page:', 'yaycheckout' ) } { summary( section, global ) }
				</div>
			) }
			{ isReverting && (
				<ConfirmModal
					title={ __( 'Use the global design?', 'yaycheckout' ) }
					lines={ changes( section, layer, global ) }
					confirmLabel={ __( 'Use global', 'yaycheckout' ) }
					onConfirm={ () => {
						setSection( undefined );
						setReverting( false );
					} }
					onCancel={ () => setReverting( false ) }
				/>
			) }
		</div>
	);
}

function RootScreen( { layer, effective, isDirty, onOpen }: { layer: Layer; effective: DesignTokens; isDirty: boolean; onOpen: ( screen: string ) => void } ) {
	const [ isLeaving, setLeaving ] = useState( false );
	const vars = designVars( effective );
	const goToGlobal = () => {
		// href, not hash: WordPress 7 isolates the block editor (Document-Isolation-Policy), so the SPA around the
		// embedded editor is cross-origin to it, and href is the one Location setter allowed across origins.
		// Inside the SPA only the hash differs, so it routes without a reload; on a plain post.php it opens the SPA.
		( window.top ?? window ).location.href = DESIGN?.page_url ?? '';
	};
	const row = ( section: string, state: string ) => (
		<Button key={ section } __next40pxDefaultSize className="yaycheckout-layout-design__row" onClick={ () => onOpen( section ) }>
			<Dashicon icon={ SECTION_ICONS[ section ] as never } />
			<span className="yaycheckout-layout-design__row-label">{ SECTION_LABELS[ section ] }</span>
			<span className="yaycheckout-layout-design__row-state">{ state }</span>
			<Dashicon icon="arrow-right-alt2" />
		</Button>
	);

	return (
		<div className="yaycheckout-layout-design">
			<Flex className="yaycheckout-layout-design__preview" justify="space-between">
				<span style={ { fontFamily: vars[ 'font-family' ] === 'initial' ? undefined : vars[ 'font-family' ] } }>Aa</span>
				<Flex justify="flex-end" gap={ 1 } expanded={ false }>
					<ColorIndicator colorValue={ String( effective.colours.primary ) } />
					<ColorIndicator colorValue={ String( effective.colours.text ) } />
				</Flex>
			</Flex>
			<div>{ Object.keys( DESIGN?.schema ?? {} ).map( ( section ) =>
				row( section, layer.tokens?.[ section ] ? __( 'Custom', 'yaycheckout' ) : __( 'Global', 'yaycheckout' ) )
			) }</div>
			<hr />
			{ row( CSS_ROW, layer.custom_css ? cssLines( layer.custom_css ) : __( 'None', 'yaycheckout' ) ) }
			<p className="yaycheckout-editor-help">
				{ __( 'Only this layout. Groups on Global follow the Design page. A block’s own styles still win.', 'yaycheckout' ) }
			</p>
			<Button variant="link" className="yaycheckout-layout-design__global" onClick={ () => ( isDirty ? setLeaving( true ) : goToGlobal() ) }>
				{ __( 'Edit global design ↗', 'yaycheckout' ) }
			</Button>
			{ isLeaving && (
				<ConfirmModal
					title={ __( 'Leave with unsaved changes?', 'yaycheckout' ) }
					lines={ [ __( 'This layout has changes that are not saved. They will be lost.', 'yaycheckout' ) ] }
					confirmLabel={ __( 'Discard and leave', 'yaycheckout' ) }
					onConfirm={ goToGlobal }
					onCancel={ () => setLeaving( false ) }
				/>
			) }
		</div>
	);
}

/** Pro: the layer editor. Rendered whether or not the sidebar is open, so the live canvas style outlives closing it. */
function LayoutDesignPro( { global }: { global: DesignTokens } ) {
	const [ screen, setScreen ] = useState< Screen >( null );
	const { rawLayer, rawSaved, isDirty } = useSelect(
		( select ) => ( {
			rawLayer: ( select( editorStore ).getEditedPostAttribute( 'meta' ) as Record< string, unknown > | undefined )?.[ META_KEY ],
			rawSaved: ( select( editorStore ).getCurrentPostAttribute( 'meta' ) as Record< string, unknown > | undefined )?.[ META_KEY ],
			isDirty: select( editorStore ).isEditedPostDirty(),
		} ),
		[]
	);
	const { editPost } = useDispatch( editorStore );
	const layer = useMemo( () => readLayer( rawLayer ), [ rawLayer ] );
	const saved = useMemo( () => readLayer( rawSaved ), [ rawSaved ] );
	const effective = useMemo( () => ( { ...global, ...layer.tokens } ), [ global, layer ] );
	const setLayer = ( next: Layer ) => editPost( { meta: { [ META_KEY ]: next } } );

	useLiveDesignStyle( effective );

	return (
		<PluginSidebar name="yaycheckout-layout-design" title={ __( 'Design', 'yaycheckout' ) } icon={ STYLES_ICON }>
			{ screen === CSS_ROW && <CssScreen layer={ layer } saved={ saved } onChange={ setLayer } onBack={ () => setScreen( null ) } /> }
			{ screen && screen !== CSS_ROW && (
				<GroupScreen section={ screen } layer={ layer } saved={ saved } global={ global } onChange={ setLayer } onBack={ () => setScreen( null ) } />
			) }
			{ ! screen && (
				<RootScreen layer={ layer } effective={ effective } isDirty={ isDirty } onOpen={ setScreen } />
			) }
		</PluginSidebar>
	);
}

/** Free: the same button, locked. No meta, no data: hard rule 3. */
function LayoutDesignTeaser() {
	return (
		<PluginSidebar name="yaycheckout-layout-design" title={ __( 'Design', 'yaycheckout' ) } icon={ STYLES_ICON }>
			<div className="yaycheckout-layout-design">
				<span className="yaycheckout-editor-pro-tag">{ __( 'Pro', 'yaycheckout' ) }</span>
				<p className="yaycheckout-editor-help">
					{ __( 'Give this layout its own colours, typography, inputs and CSS.', 'yaycheckout' ) }
				</p>
				<Button __next40pxDefaultSize variant="primary" href={ editorData.upgradeUrl } target="_blank">
					{ __( 'Upgrade', 'yaycheckout' ) }
				</Button>
			</div>
		</PluginSidebar>
	);
}

function LayoutDesign() {
	const postType = useSelect( ( select ) => select( editorStore ).getCurrentPostType(), [] );

	if ( postType !== editorData.postType ) {
		return null;
	}

	return editorData.isPro && DESIGN ? <LayoutDesignPro global={ DESIGN.tokens } /> : <LayoutDesignTeaser />;
}

registerPlugin( 'yaycheckout-layout-design', {
	render: LayoutDesign,
} );
