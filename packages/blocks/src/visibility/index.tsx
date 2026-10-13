/**
 * The block visibility setting in the editor. BlockRegistry enqueues it in every editor, so markup saved
 * in a layout stays valid anywhere; the Visibility panel and the preview sidebar show in the layout editor only.
 */
import { InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import {
	Button,
	Flex,
	FlexBlock,
	Notice,
	PanelBody,
	SelectControl,
	ToggleControl,
	__experimentalInputControlSuffixWrapper as InputControlSuffixWrapper,
	__experimentalNumberControl as NumberControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { createReduxStore, register, select as selectNow, useDispatch, useSelect } from '@wordpress/data';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { useEffect, useMemo, useRef, type ComponentType } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { editorData } from '../shared/editor-data';
import { useIsLayoutEditor } from '../shared/layout-editor';
import { plainText } from '../shared/plain-text';
import { stepTitle } from '../shared/step-title';
import {
	ATTRIBUTE,
	EXCLUDED,
	isVisible,
	normalize,
	type VisibilityRule,
	type VisibilitySetting,
} from '../shared/visibility-rules';
import './editor.css';

type BlockSettings = { attributes?: Record< string, unknown > };

type RuleType = VisibilityRule[ 'type' ];

/** A rule as the panel edits it: the stored shape, which normalize() may still reject. */
type DraftRule = { type: RuleType; step?: number | 'last'; op?: 'gte' | 'lt'; amount?: number };

type DraftSetting = { action: 'show' | 'hide'; rules: DraftRule[] };

type StepOption = { value: number | 'last'; label: string };

const STEPS = 'yaycheckout/steps';

const STEP = 'yaycheckout/step';

/** Blocks holding WooCommerce fields, whose required rules stay while a container hides. */
const FIELD_BLOCKS = [
	'yaycheckout/billing',
	'yaycheckout/shipping',
	'yaycheckout/field',
	'yaycheckout/email',
	'yaycheckout/order-note',
	'yaycheckout/extra-field',
];

const CONDITIONAL_CLASS = 'is-yaycheckout-conditional';

const PREVIEW_HIDDEN_CLASS = 'is-yaycheckout-preview-hidden';

const PLUGIN = 'yaycheckout-visibility-preview';

const STORE = 'yaycheckout/visibility-preview';

addFilter( 'blocks.registerBlockType', 'yaycheckout/visibility', ( settings: BlockSettings, name: string ) =>
	EXCLUDED.includes( name ) ? settings : { ...settings, attributes: { ...settings.attributes, [ ATTRIBUTE ]: { type: 'object' } } }
);

function typeLabels(): Record< RuleType, string > {
	return {
		step: __( 'Step', 'yaycheckout' ),
		needs_shipping: __( 'Needs shipping', 'yaycheckout' ),
		has_coupon: __( 'Coupon applied', 'yaycheckout' ),
		subtotal: __( 'Subtotal', 'yaycheckout' ),
		logged_in: __( 'Logged in', 'yaycheckout' ),
	};
}

/**
 * "1. <title>" … for the editor's first Steps block, then "Last step"; only "Last step" without one.
 * Mirrors CheckoutStepsProgress::layout_titles(), which counts the steps the storefront numbers.
 */
export function stepOptions( registrySelect: typeof selectNow = selectNow ): StepOption[] {
	const { getBlockAttributes, getBlockName, getBlockOrder, getBlocksByName } = registrySelect( blockEditorStore );
	const stepsId = ( getBlocksByName( STEPS ) as string[] )[ 0 ];
	const steps = stepsId ? ( getBlockOrder( stepsId ) as string[] ).filter( ( id ) => getBlockName( id ) === STEP ) : [];

	return [
		...steps.map( ( id, index ) => ( {
			value: index + 1,
			label: `${ index + 1 }. ${ stepTitle( plainText( getBlockAttributes( id )?.title ) ) }`,
		} ) ),
		{ value: 'last' as const, label: __( 'Last step', 'yaycheckout' ) },
	];
}

/** stepOptions(), re-read as the layout's steps change. */
function useStepOptions(): StepOption[] {
	const key = useSelect( ( select ) => JSON.stringify( stepOptions( select ) ), [] );

	return useMemo( () => JSON.parse( key ) as StepOption[], [ key ] );
}

function newRule( type: RuleType, steps: StepOption[] ): DraftRule {
	if ( type === 'step' ) {
		return { type, step: steps[ 0 ].value };
	}

	return type === 'subtotal' ? { type, op: 'gte', amount: 0 } : { type };
}

/** The stored attribute as the panel edits it; null while the block has none. */
function draftOf( value: unknown ): DraftSetting | null {
	if ( value === null || typeof value !== 'object' ) {
		return null;
	}

	const { action, rules } = value as { action?: unknown; rules?: unknown };

	if ( action !== 'show' && action !== 'hide' ) {
		return null;
	}

	return {
		action,
		rules: Array.isArray( rules ) ? rules.filter( ( rule ): rule is DraftRule => !! rule && typeof rule === 'object' ) : [],
	};
}

function money( amount: number ): string {
	return `${ editorData.currencySymbol }${ amount }`;
}

/** One line saying when the block shows, e.g. "Hidden on the last step" or "Shown when logged in and a coupon is applied". */
function summary( setting: VisibilitySetting | null, draft: DraftSetting ): string {
	if ( ! setting ) {
		return draft.action === 'show'
			? __( 'Add a condition. Until then, the block always shows.', 'yaycheckout' )
			: __( 'Add a condition. Until then, the block never hides.', 'yaycheckout' );
	}

	let where = '';
	const when: string[] = [];

	setting.rules.forEach( ( rule ) => {
		switch ( rule.type ) {
			case 'step':
				where =
					rule.step === 'last'
						? __( 'on the last step', 'yaycheckout' )
						: /* translators: %d: step number. */ sprintf( __( 'on step %d', 'yaycheckout' ), rule.step );
				break;
			case 'needs_shipping':
				when.push( __( 'the cart needs shipping', 'yaycheckout' ) );
				break;
			case 'has_coupon':
				when.push( __( 'a coupon is applied', 'yaycheckout' ) );
				break;
			case 'subtotal':
				when.push(
					rule.op === 'gte'
						? /* translators: %s: amount with currency symbol. */ sprintf( __( 'the subtotal is at least %s', 'yaycheckout' ), money( rule.amount ) )
						: /* translators: %s: amount with currency symbol. */ sprintf( __( 'the subtotal is below %s', 'yaycheckout' ), money( rule.amount ) )
				);
				break;
			case 'logged_in':
				when.push( __( 'logged in', 'yaycheckout' ) );
				break;
		}
	} );

	const conditions = [
		where,
		/* translators: %s: conditions joined by "and", e.g. "logged in and a coupon is applied". */
		when.length > 0 ? sprintf( __( 'when %s', 'yaycheckout' ), when.join( __( ' and ', 'yaycheckout' ) ) ) : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return setting.action === 'show'
		? /* translators: %s: when the block shows, e.g. "on the last step". */ sprintf( __( 'Shown %s', 'yaycheckout' ), conditions )
		: /* translators: %s: when the block hides, e.g. "on the last step". */ sprintf( __( 'Hidden %s', 'yaycheckout' ), conditions );
}

/** The store's currency symbol as an input's suffix. */
function CurrencySuffix() {
	return <InputControlSuffixWrapper>{ editorData.currencySymbol }</InputControlSuffixWrapper>;
}

type PanelProps = {
	clientId: string;
	attributes: Record< string, unknown >;
	setAttributes: ( attributes: Record< string, unknown > ) => void;
};

type RuleRowProps = {
	rule: DraftRule;
	used: RuleType[];
	steps: StepOption[];
	onChange: ( rule: DraftRule ) => void;
	onRemove: () => void;
};

function RuleRow( { rule, used, steps, onChange, onRemove }: RuleRowProps ) {
	const labels = typeLabels();
	const stepValues = steps.map( ( option ) => String( option.value ) );
	const stepValue = String( rule.step ?? '' );

	return (
		<div className="yaycheckout-visibility__rule">
			<Flex align="flex-end">
				<FlexBlock>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Condition', 'yaycheckout' ) }
						value={ rule.type }
						options={ ( Object.keys( labels ) as RuleType[] )
							.filter( ( type ) => type === rule.type || ! used.includes( type ) )
							.map( ( type ) => ( { value: type, label: labels[ type ] } ) ) }
						onChange={ ( type: string ) => onChange( newRule( type as RuleType, steps ) ) }
					/>
				</FlexBlock>
				<Button __next40pxDefaultSize icon="no-alt" label={ __( 'Remove condition', 'yaycheckout' ) } onClick={ onRemove } />
			</Flex>
			{ rule.type === 'step' && (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Step is', 'yaycheckout' ) }
					value={ stepValue }
					options={ [
						...steps.map( ( option ) => ( { value: String( option.value ), label: option.label } ) ),
						// A step deleted since: kept selected, so the merchant sees it rather than another step.
						...( stepValues.includes( stepValue )
							? []
							: [
									{
										value: stepValue,
										/* translators: %s: saved step number. */
										label: sprintf( __( '%s. (no longer exists)', 'yaycheckout' ), stepValue ),
									},
							  ] ),
					] }
					onChange={ ( value: string ) => onChange( { ...rule, step: value === 'last' ? 'last' : Number( value ) } ) }
				/>
			) }
			{ rule.type === 'subtotal' && (
				<Flex align="flex-end">
					<FlexBlock>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Subtotal is', 'yaycheckout' ) }
							value={ rule.op ?? 'gte' }
							options={ [
								{ value: 'gte', label: __( 'at least', 'yaycheckout' ) },
								{ value: 'lt', label: __( 'below', 'yaycheckout' ) },
							] }
							onChange={ ( op: string ) => onChange( { ...rule, op: op as 'gte' | 'lt' } ) }
						/>
					</FlexBlock>
					<FlexBlock>
						<NumberControl
							__next40pxDefaultSize
							label={ __( 'Amount', 'yaycheckout' ) }
							min={ 0 }
							step={ 0.01 }
							value={ rule.amount ?? 0 }
							suffix={ <CurrencySuffix /> }
							onChange={ ( value?: string ) => {
								const amount = Number( value );

								onChange( { ...rule, amount: Number.isFinite( amount ) && amount >= 0 ? amount : 0 } );
							} }
						/>
					</FlexBlock>
				</Flex>
			) }
		</div>
	);
}

function VisibilityPanel( { clientId, attributes, setAttributes }: PanelProps ) {
	const draft = draftOf( attributes[ ATTRIBUTE ] );
	const setting = normalize( attributes[ ATTRIBUTE ] );
	const steps = useStepOptions();
	const { hasSteps, hasFields } = useSelect(
		( select ) => {
			const { getBlockName, getBlocksByName, getClientIdsOfDescendants } = select( blockEditorStore );

			return {
				hasSteps: ( getBlocksByName( STEPS ) as string[] ).length > 0,
				hasFields: [ clientId, ...( getClientIdsOfDescendants( [ clientId ] ) as string[] ) ].some( ( id ) =>
					FIELD_BLOCKS.includes( getBlockName( id ) )
				),
			};
		},
		[ clientId ]
	);

	const write = ( next: DraftSetting | null ) => setAttributes( { [ ATTRIBUTE ]: next ?? undefined } );
	const rules = draft?.rules ?? [];
	const used = rules.map( ( rule ) => rule.type );
	const unused = ( Object.keys( typeLabels() ) as RuleType[] ).filter( ( type ) => ! used.includes( type ) );

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Visibility', 'yaycheckout' ) } initialOpen={ !! draft }>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Visibility', 'yaycheckout' ) }
					value={ draft?.action ?? 'always' }
					options={ [
						{ value: 'always', label: __( 'Always', 'yaycheckout' ) },
						{ value: 'show', label: __( 'Show when…', 'yaycheckout' ) },
						{ value: 'hide', label: __( 'Hide when…', 'yaycheckout' ) },
					] }
					onChange={ ( action: string ) => write( action === 'show' || action === 'hide' ? { action, rules } : null ) }
				/>
				{ draft && (
					<>
						{ rules.map( ( rule, index ) => (
							<RuleRow
								key={ rule.type }
								rule={ rule }
								used={ used }
								steps={ steps }
								onChange={ ( next ) => write( { ...draft, rules: rules.map( ( kept, at ) => ( at === index ? next : kept ) ) } ) }
								onRemove={ () => write( { ...draft, rules: rules.filter( ( _, at ) => at !== index ) } ) }
							/>
						) ) }
						{ unused.length > 0 && (
							<Button
								variant="secondary"
								className="yaycheckout-visibility__add"
								onClick={ () => write( { ...draft, rules: [ ...rules, newRule( unused[ 0 ], steps ) ] } ) }
							>
								{ __( 'Add condition', 'yaycheckout' ) }
							</Button>
						) }
						<p className="yaycheckout-visibility__summary">{ summary( setting, draft ) }</p>
						{ ! hasSteps && used.includes( 'step' ) && (
							<Notice status="warning" isDismissible={ false }>
								{ __( 'This layout has no Steps block, so step conditions never match.', 'yaycheckout' ) }
							</Notice>
						) }
						{ hasFields && (
							<Notice status="info" isDismissible={ false }>
								{ __( 'Fields inside stay required while this block is hidden.', 'yaycheckout' ) }
							</Notice>
						) }
					</>
				) }
			</PanelBody>
		</InspectorControls>
	);
}

function LayoutOnlyPanel( props: PanelProps ) {
	return useIsLayoutEditor() ? <VisibilityPanel { ...props } /> : null;
}

type BlockEditProps = PanelProps & { name: string; isSelected: boolean };

const withVisibilityPanel = createHigherOrderComponent(
	( BlockEdit: ComponentType< BlockEditProps > ) =>
		function VisibilityBlockEdit( props: BlockEditProps ) {
			return (
				<>
					<BlockEdit { ...props } />
					{ props.isSelected && ! EXCLUDED.includes( props.name ) && <LayoutOnlyPanel { ...props } /> }
				</>
			);
		},
	'withVisibilityPanel'
);

addFilter( 'editor.BlockEdit', 'yaycheckout/visibility/panel', withVisibilityPanel );

/** The preview sidebar's inputs; in memory only, never in the post. */
type Preview = {
	open: boolean;
	step: 'off' | number | 'last';
	logged_in: boolean;
	needs_shipping: boolean;
	has_coupon: boolean;
	subtotal: number;
};

const NO_PREVIEW: Preview = {
	open: false,
	step: 'off',
	logged_in: false,
	needs_shipping: false,
	has_coupon: false,
	subtotal: 0,
};

const previewStore = createReduxStore( STORE, {
	reducer( state: Preview = NO_PREVIEW, action: { type: string; preview?: Partial< Preview > } ): Preview {
		switch ( action.type ) {
			case 'SET_PREVIEW':
				return { ...state, ...action.preview };
			case 'RESET_PREVIEW':
				return NO_PREVIEW;
			default:
				return state;
		}
	},
	actions: {
		setPreview: ( preview: Partial< Preview > ) => ( { type: 'SET_PREVIEW', preview } ),
		resetPreview: () => ( { type: 'RESET_PREVIEW' } ),
	},
	selectors: {
		getPreview: ( state: Preview ): Preview => state,
	},
} );

register( previewStore );

/** Whether the preview dims anything: the sidebar is open and a step, a toggle or an amount is set. */
function isPreviewing( preview: Preview ): boolean {
	return (
		preview.open &&
		( preview.step !== 'off' || preview.logged_in || preview.needs_shipping || preview.has_coupon || preview.subtotal > 0 )
	);
}

/**
 * Whether the preview inputs would hide a block with this setting. With Step off, step conditions are left
 * out, so the preview shows what the other inputs alone decide.
 */
function previewHides( setting: VisibilitySetting, preview: Preview, stepCount: number ): boolean {
	const state = {
		logged_in: preview.logged_in,
		needs_shipping: preview.needs_shipping,
		has_coupon: preview.has_coupon,
		subtotal: preview.subtotal,
	};

	if ( preview.step === 'off' ) {
		const rules = setting.rules.filter( ( rule ) => rule.type !== 'step' );

		return rules.length > 0 && ! isVisible( { ...setting, rules }, state, 0, stepCount );
	}

	return ! isVisible( setting, state, preview.step === 'last' ? stepCount : preview.step, stepCount );
}

type ListBlockProps = { attributes: Record< string, unknown >; className?: string; wrapperProps?: Record< string, unknown > };

type ConditionalBlockProps = ListBlockProps & { BlockListBlock: ComponentType< ListBlockProps >; setting: VisibilitySetting };

/** A block with a setting: the Conditional tag, and the preview's dimming. */
function ConditionalBlock( { BlockListBlock, setting, ...props }: ConditionalBlockProps ) {
	const hidden = useSelect(
		( select ) => {
			const preview: Preview = select( STORE ).getPreview();

			// stepOptions() ends with "Last step", so the steps are one fewer.
			return isPreviewing( preview ) && previewHides( setting, preview, stepOptions( select ).length - 1 );
		},
		[ setting ]
	);
	const className = [ props.className, CONDITIONAL_CLASS, hidden ? PREVIEW_HIDDEN_CLASS : '' ].filter( Boolean ).join( ' ' );

	return (
		<BlockListBlock
			{ ...props }
			className={ className }
			wrapperProps={ { ...props.wrapperProps, 'data-yaycheckout-conditional': __( 'Conditional', 'yaycheckout' ) } }
		/>
	);
}

const withConditionalMarker = createHigherOrderComponent(
	( BlockListBlock: ComponentType< ListBlockProps > ) =>
		function VisibilityBlockListBlock( props: ListBlockProps ) {
			const value = props.attributes?.[ ATTRIBUTE ];
			// Keyed on the stored JSON, so the preview's selector keeps one setting object between renders.
			const key = value === undefined ? '' : JSON.stringify( value );
			const setting = useMemo( () => ( key === '' ? null : normalize( JSON.parse( key ) ) ), [ key ] );

			return setting ? (
				<ConditionalBlock { ...props } BlockListBlock={ BlockListBlock } setting={ setting } />
			) : (
				<BlockListBlock { ...props } />
			);
		},
	'withConditionalMarker'
);

addFilter( 'editor.BlockListBlock', 'yaycheckout/visibility/marker', withConditionalMarker );

function PreviewSidebar() {
	const preview: Preview = useSelect( ( select ) => select( STORE ).getPreview(), [] );
	const { setPreview, resetPreview } = useDispatch( STORE );
	const steps = useStepOptions();
	const active = useSelect(
		( select ) => select( 'core/interface' ).getActiveComplementaryArea( 'core' ) === `${ PLUGIN }/${ PLUGIN }`,
		[]
	);
	const wasActive = useRef( false );

	// Open follows the sidebar; closing it clears every input.
	useEffect( () => {
		if ( active ) {
			setPreview( { open: true } );
		} else if ( wasActive.current ) {
			resetPreview();
		}

		wasActive.current = active;
	}, [ active ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<>
			<PluginSidebarMoreMenuItem target={ PLUGIN } icon="visibility">
				{ __( 'Visibility preview', 'yaycheckout' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar name={ PLUGIN } title={ __( 'Visibility preview', 'yaycheckout' ) } icon="visibility">
				<PanelBody>
					<VStack spacing={ 4 }>
						<p className="yaycheckout-visibility__help">
							{ __( 'Dims the blocks these inputs would hide on the checkout. Nothing here is saved.', 'yaycheckout' ) }
						</p>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Step', 'yaycheckout' ) }
							value={ String( preview.step ) }
							options={ [
								{ value: 'off', label: __( 'Off', 'yaycheckout' ) },
								...steps.map( ( option ) => ( { value: String( option.value ), label: option.label } ) ),
							] }
							onChange={ ( value: string ) =>
								setPreview( { step: value === 'off' || value === 'last' ? value : Number( value ) } )
							}
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Logged in', 'yaycheckout' ) }
							checked={ preview.logged_in }
							onChange={ ( logged_in: boolean ) => setPreview( { logged_in } ) }
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Needs shipping', 'yaycheckout' ) }
							checked={ preview.needs_shipping }
							onChange={ ( needs_shipping: boolean ) => setPreview( { needs_shipping } ) }
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Coupon applied', 'yaycheckout' ) }
							checked={ preview.has_coupon }
							onChange={ ( has_coupon: boolean ) => setPreview( { has_coupon } ) }
						/>
						<NumberControl
							__next40pxDefaultSize
							label={ __( 'Subtotal', 'yaycheckout' ) }
							min={ 0 }
							step={ 0.01 }
							value={ preview.subtotal }
							suffix={ <CurrencySuffix /> }
							onChange={ ( value?: string ) => {
								const subtotal = Number( value );

								setPreview( { subtotal: Number.isFinite( subtotal ) && subtotal >= 0 ? subtotal : 0 } );
							} }
						/>
					</VStack>
				</PanelBody>
			</PluginSidebar>
		</>
	);
}

registerPlugin( PLUGIN, {
	render: function VisibilityPreview() {
		return useIsLayoutEditor() ? <PreviewSidebar /> : null;
	},
} );
