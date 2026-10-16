/**
 * The block visibility setting in the editor. BlockRegistry enqueues it in every editor, so markup saved
 * in a layout stays valid anywhere; the Visibility panel, the preview sidebar and the blockId keeper run
 * in the layout editor only.
 */
import { InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { getBlockType, hasBlockSupport } from '@wordpress/blocks';
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
import { Fragment, useEffect, useMemo, useRef, type ComponentType } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { editorData } from '../shared/editor-data';
import { useIsLayoutEditor } from '../shared/layout-editor';
import { addLayoutPanel, type LayoutPanelProps } from '../shared/layout-panel';
import { firstStepsBlock } from '../shared/layout-steps';
import { plainText } from '../shared/plain-text';
import { stepTitle } from '../shared/step-title';
import {
	ATTRIBUTE,
	EXCLUDED,
	isVisible,
	normalize,
	WATCHABLE_SUPPORT,
	type VisibilityRule,
	type VisibilitySetting,
	type VisibilityState,
} from '../shared/visibility-rules';
import stepMeta from '../pro/step/block.json';
import './editor.css';

type RuleType = VisibilityRule[ 'type' ];

/** A rule as the panel edits it: the stored shape, which normalize() may still reject. */
type DraftRule = {
	type: RuleType;
	step?: number | 'last';
	op?: 'gte' | 'lt';
	amount?: number;
	block?: string;
	is?: 'empty' | 'not_empty';
};

/** A watchable block a "block" rule can point at; blockId is empty until one is given. */
type Target = { clientId: string; blockId: string; label: string };

type DraftSetting = { action: 'show' | 'hide'; rules: DraftRule[] };

type StepOption = { value: number | 'last'; label: string };

const STEP = stepMeta.name;

const CONDITIONAL_CLASS = 'is-yaycheckout-conditional';

const PREVIEW_HIDDEN_CLASS = 'is-yaycheckout-preview-hidden';

const PREVIEW_COLLAPSED_CLASS = 'is-yaycheckout-preview-collapsed';

const PLUGIN = 'yaycheckout-visibility-preview';

const STORE = 'yaycheckout/visibility-preview';

function typeLabels(): Record< RuleType, string > {
	return {
		step: __( 'Step', 'yaycheckout' ),
		needs_shipping: __( 'Needs shipping', 'yaycheckout' ),
		has_coupon: __( 'Coupon applied', 'yaycheckout' ),
		subtotal: __( 'Subtotal', 'yaycheckout' ),
		logged_in: __( 'Logged in', 'yaycheckout' ),
		block: __( 'Block', 'yaycheckout' ),
	};
}

/** A new blockId. Mirrors BlockEmptiness::BLOCK_ID_PATTERN in PHP: 8 characters from [a-z0-9]. */
function newBlockId(): string {
	const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	const bytes = crypto.getRandomValues( new Uint8Array( 8 ) );

	return Array.from( bytes, ( byte ) => chars[ byte % chars.length ] ).join( '' );
}

/** The watchable blocks in the layout, in order, each named by its title and which Slot or Field it is. */
function targetsIn( registrySelect: typeof selectNow ): Target[] {
	const { getBlockName, getBlockAttributes, getClientIdsWithDescendants } = registrySelect( blockEditorStore );

	return ( getClientIdsWithDescendants() as string[] )
		.filter( ( id ) => hasBlockSupport( getBlockName( id ), WATCHABLE_SUPPORT ) )
		.map( ( id ) => {
			const name = getBlockName( id );
			const attrs = getBlockAttributes( id ) ?? {};
			const title = getBlockType( name )?.title ?? name;
			// A layout can hold several Slots or Fields, and billing and shipping share labels.
			const fieldLabel = plainText( attrs.label );
			let detail = String( attrs.slotName ?? '' );

			if ( attrs.fieldKey ) {
				detail = fieldLabel ? `${ fieldLabel } (${ attrs.fieldKey })` : String( attrs.fieldKey );
			} else if ( Array.isArray( attrs.fieldKeys ) ) {
				detail = attrs.fieldKeys.join( ', ' );
			}

			return {
				clientId: id,
				blockId: typeof attrs.blockId === 'string' ? attrs.blockId : '',
				label: detail ? `${ title } · ${ detail }` : title,
			};
		} );
}

/** targetsIn(), rebuilt only when a watchable block is added, removed, renamed or given a blockId. */
function useTargets(): Target[] {
	const targets = useSelect( ( select ) => JSON.stringify( targetsIn( select ) ), [] );

	return useMemo( () => JSON.parse( targets ) as Target[], [ targets ] );
}

/** The Step blocks of the layout's first Steps block, in order; empty without one. */
function stepIds( registrySelect: typeof selectNow ): string[] {
	const { getBlockName, getBlockOrder } = registrySelect( blockEditorStore );
	const stepsId = firstStepsBlock( registrySelect );

	return stepsId ? ( getBlockOrder( stepsId ) as string[] ).filter( ( id ) => getBlockName( id ) === STEP ) : [];
}

/** "1. <title>" … for the editor's first Steps block, then "Last step"; only "Last step" without one. */
export function stepOptions( registrySelect: typeof selectNow = selectNow ): StepOption[] {
	const { getBlockAttributes } = registrySelect( blockEditorStore );

	return optionsFor( stepIds( registrySelect ).map( ( id ) => getBlockAttributes( id )?.title ) );
}

function optionsFor( titles: unknown[] ): StepOption[] {
	return [
		...titles.map( ( title, index ) => ( {
			value: index + 1,
			label: `${ index + 1 }. ${ stepTitle( plainText( title ) ) }`,
		} ) ),
		{ value: 'last' as const, label: __( 'Last step', 'yaycheckout' ) },
	];
}

/** stepOptions(), rebuilt only when a step is added, removed or renamed. */
function useStepOptions(): StepOption[] {
	const titles = useSelect( ( select ) => {
		const { getBlockAttributes } = select( blockEditorStore );

		return JSON.stringify( stepIds( select ).map( ( id ) => getBlockAttributes( id )?.title ?? '' ) );
	}, [] );

	return useMemo( () => optionsFor( JSON.parse( titles ) as unknown[] ), [ titles ] );
}

/** A step select's options: stepOptions() with string values. */
function stepSelectOptions( steps: StepOption[] ): { value: string; label: string }[] {
	return steps.map( ( option ) => ( { value: String( option.value ), label: option.label } ) );
}

/** A step select's value back as a step: "last" or its number. */
function parseStep( value: string ): number | 'last' {
	return value === 'last' ? 'last' : Number( value );
}

/** A typed amount, or 0 when it is empty, negative or not a number. */
function toAmount( value?: string ): number {
	const amount = Number( value );

	return Number.isFinite( amount ) && amount >= 0 ? amount : 0;
}

function newRule( type: RuleType, steps: StepOption[], block = '' ): DraftRule {
	if ( type === 'step' ) {
		return { type, step: steps[ 0 ].value };
	}

	if ( type === 'block' ) {
		return { type, block, is: 'empty' };
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

/** The store's currency symbol as an input's suffix. */
function CurrencySuffix() {
	return <InputControlSuffixWrapper>{ editorData.currencySymbol }</InputControlSuffixWrapper>;
}

type RuleRowProps = {
	rule: DraftRule;
	types: RuleType[];
	steps: StepOption[];
	/** The targets this row can pick: every one not picked by another row. */
	targets: Target[];
	/** The blockId of a target, given one first if it has none. */
	blockIdOf: ( target: Target ) => string;
	/** A new rule of a type, with a free target for "block". */
	ruleFor: ( type: RuleType ) => DraftRule;
	onChange: ( rule: DraftRule ) => void;
	onRemove: () => void;
};

/** A target select's value: the blockId, or the clientId of a block that has none yet. */
function targetValue( target: Target ): string {
	return target.blockId || `client:${ target.clientId }`;
}

function RuleRow( { rule, types, steps, targets, blockIdOf, ruleFor, onChange, onRemove }: RuleRowProps ) {
	const labels = typeLabels();
	const stepValues = steps.map( ( option ) => String( option.value ) );
	const stepValue = String( rule.step ?? '' );
	const block = rule.block ?? '';

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
							.filter( ( type ) => type === rule.type || types.includes( type ) )
							.map( ( type ) => ( { value: type, label: labels[ type ] } ) ) }
						onChange={ ( type: string ) => onChange( ruleFor( type as RuleType ) ) }
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
						...stepSelectOptions( steps ),
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
					onChange={ ( value: string ) => onChange( { ...rule, step: parseStep( value ) } ) }
				/>
			) }
			{ rule.type === 'block' && (
				<>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Block', 'yaycheckout' ) }
						value={ block }
						options={ [
							...targets.map( ( target ) => ( { value: targetValue( target ), label: target.label } ) ),
							// A block deleted since: kept selected, so the merchant sees it rather than another block.
							...( targets.some( ( target ) => target.blockId === block )
								? []
								: [ { value: block, label: __( '(block removed)', 'yaycheckout' ) } ] ),
						] }
						onChange={ ( value: string ) => {
							const target = targets.find( ( candidate ) => targetValue( candidate ) === value );

							onChange( { ...rule, block: target ? blockIdOf( target ) : value } );
						} }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Block is', 'yaycheckout' ) }
						value={ rule.is ?? 'empty' }
						options={ [
							{ value: 'empty', label: __( 'empty', 'yaycheckout' ) },
							{ value: 'not_empty', label: __( 'not empty', 'yaycheckout' ) },
						] }
						onChange={ ( is: string ) => onChange( { ...rule, is: is as 'empty' | 'not_empty' } ) }
					/>
				</>
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
							onChange={ ( value?: string ) => onChange( { ...rule, amount: toAmount( value ) } ) }
						/>
					</FlexBlock>
				</Flex>
			) }
		</div>
	);
}

/** Whether a block inside clientId, other than the targets, is a required field. */
function useHasRequiredField( clientId: string, targets: string[] ): boolean {
	return useSelect(
		( select ) => {
			const { getBlockName, getBlockAttributes, getClientIdsOfDescendants } = select( blockEditorStore );

			// Any block whose block.json declares `required` (Field, Email, Extra field), except a target: a
			// Field this block depends on hides only when its own country hides it.
			return ( getClientIdsOfDescendants( [ clientId ] ) as string[] ).some( ( id ) => {
				const attrs = getBlockAttributes( id ) ?? {};

				return (
					! targets.includes( String( attrs.blockId ?? '' ) ) &&
					!! getBlockType( getBlockName( id ) )?.attributes?.required &&
					attrs.required === true
				);
			} );
		},
		[ clientId, targets.join( ' ' ) ]
	);
}

function VisibilityPanel( { clientId, attributes, setAttributes }: LayoutPanelProps ) {
	const draft = draftOf( attributes[ ATTRIBUTE ] );
	const steps = useStepOptions();
	const targets = useTargets();
	const { updateBlockAttributes } = useDispatch( blockEditorStore );

	const write = ( next: DraftSetting | null ) => setAttributes( { [ ATTRIBUTE ]: next ?? undefined } );
	const rules = draft?.rules ?? [];
	const picked = rules.flatMap( ( rule ) => ( rule.type === 'block' && rule.block ? [ rule.block ] : [] ) );
	const free = targets.filter( ( target ) => target.blockId === '' || ! picked.includes( target.blockId ) );
	const hasRequiredField = useHasRequiredField( clientId, picked );
	// Each type once, except "block", offered while a target is left to pick. No "step" where Step is a
	// locked Pro stub: the storefront skips step rules there.
	const unused = ( Object.keys( typeLabels() ) as RuleType[] ).filter( ( type ) => {
		if ( type === 'step' && editorData.lockedBlocks.includes( stepMeta.name ) ) {
			return false;
		}

		return type === 'block' ? free.length > 0 : ! rules.some( ( rule ) => rule.type === type );
	} );

	const blockIdOf = ( target: Target ): string => {
		if ( target.blockId !== '' ) {
			return target.blockId;
		}

		const blockId = newBlockId();

		updateBlockAttributes( target.clientId, { blockId } );

		return blockId;
	};

	const ruleFor = ( type: RuleType ): DraftRule => newRule( type, steps, type === 'block' && free[ 0 ] ? blockIdOf( free[ 0 ] ) : '' );

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Visibility', 'yaycheckout' ) } initialOpen={ !! draft }>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Display', 'yaycheckout' ) }
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
							<Fragment key={ `${ index }-${ rule.type }` }>
								{ index > 0 && <p className="yaycheckout-visibility__and">{ __( 'and', 'yaycheckout' ) }</p> }
								<RuleRow
									rule={ rule }
									types={ unused }
									steps={ steps }
									targets={ targets.filter(
										( target ) => target.blockId === rule.block || ! picked.includes( target.blockId )
									) }
									blockIdOf={ blockIdOf }
									ruleFor={ ruleFor }
									onChange={ ( next ) => write( { ...draft, rules: rules.map( ( kept, at ) => ( at === index ? next : kept ) ) } ) }
									onRemove={ () => write( { ...draft, rules: rules.filter( ( _, at ) => at !== index ) } ) }
								/>
							</Fragment>
						) ) }
						{ unused.length > 0 && (
							<Button
								variant="secondary"
								className="yaycheckout-visibility__add"
								onClick={ () => write( { ...draft, rules: [ ...rules, ruleFor( unused[ 0 ] ) ] } ) }
							>
								{ __( 'Add condition', 'yaycheckout' ) }
							</Button>
						) }
						{ picked.length > 0 && hasRequiredField && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									"This block contains required fields. If it hides, shoppers can't complete checkout.",
									'yaycheckout'
								) }
							</Notice>
						) }
					</>
				) }
			</PanelBody>
		</InspectorControls>
	);
}

addLayoutPanel( 'yaycheckout/visibility', ( name ) => ! EXCLUDED.includes( name ), ATTRIBUTE, { type: 'object' }, VisibilityPanel );

/** The preview sidebar's inputs; in memory only, never in the post. */
type Preview = VisibilityState & { open: boolean; step: 'off' | number | 'last'; collapse: boolean };

const NO_PREVIEW: Preview = {
	open: false,
	step: 'off',
	collapse: false,
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

/**
 * Whether the preview inputs would hide a block with this setting. Block conditions are left out, and with
 * Step off step conditions too, so the preview shows what the other inputs alone decide.
 */
function previewHides( setting: VisibilitySetting, preview: Preview, stepCount: number ): boolean {
	const rules = setting.rules.filter(
		( rule ) => rule.type !== 'block' && ( preview.step !== 'off' || rule.type !== 'step' )
	);
	const step = preview.step === 'last' ? stepCount : preview.step;

	return rules.length > 0 && ! isVisible( { ...setting, rules }, preview, step === 'off' ? 0 : step, stepCount );
}

type ListBlockProps = { attributes: Record< string, unknown >; className?: string; wrapperProps?: Record< string, unknown > };

type ConditionalBlockProps = ListBlockProps & { BlockListBlock: ComponentType< ListBlockProps >; setting: VisibilitySetting };

/** A block with a setting: the Conditional tag, and the preview's dimming or collapsing. */
function ConditionalBlock( { BlockListBlock, setting, ...props }: ConditionalBlockProps ) {
	const previewClass = useSelect(
		( select ) => {
			const preview: Preview = select( STORE ).getPreview();

			if ( ! preview.open || ! previewHides( setting, preview, stepIds( select ).length ) ) {
				return '';
			}

			return preview.collapse ? PREVIEW_COLLAPSED_CLASS : PREVIEW_HIDDEN_CLASS;
		},
		[ setting ]
	);
	const className = [ props.className, CONDITIONAL_CLASS, previewClass ].filter( Boolean ).join( ' ' );

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
			// The attribute keeps its identity until it is set again, so the preview's selector keeps one setting.
			const setting = useMemo( () => normalize( value ), [ value ] );

			return setting ? (
				<ConditionalBlock { ...props } BlockListBlock={ BlockListBlock } setting={ setting } />
			) : (
				<BlockListBlock { ...props } />
			);
		},
	'withConditionalMarker'
);

addFilter( 'editor.BlockListBlock', 'yaycheckout/visibility/marker', withConditionalMarker );

/** The preview's yes/no inputs, in the panel's order. */
const TOGGLES = [ 'logged_in', 'needs_shipping', 'has_coupon' ] as const;

function PreviewSidebar() {
	const preview: Preview = useSelect( ( select ) => select( STORE ).getPreview(), [] );
	const { setPreview, resetPreview } = useDispatch( STORE );
	const steps = useStepOptions();
	const labels = typeLabels();
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
							{ __(
								"Dims the blocks these inputs would hide on the checkout. Block conditions aren't previewed. Nothing here is saved.",
								'yaycheckout'
							) }
						</p>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Step', 'yaycheckout' ) }
							value={ String( preview.step ) }
							options={ [
								{ value: 'off', label: __( 'Off', 'yaycheckout' ) },
								...stepSelectOptions( steps ),
							] }
							onChange={ ( value: string ) => setPreview( { step: value === 'off' ? 'off' : parseStep( value ) } ) }
						/>
						{ TOGGLES.map( ( key ) => (
							<ToggleControl
								key={ key }
								__nextHasNoMarginBottom
								label={ labels[ key ] }
								checked={ preview[ key ] }
								onChange={ ( checked: boolean ) => setPreview( { [ key ]: checked } ) }
							/>
						) ) }
						<NumberControl
							__next40pxDefaultSize
							label={ __( 'Subtotal', 'yaycheckout' ) }
							min={ 0 }
							step={ 0.01 }
							value={ preview.subtotal }
							suffix={ <CurrencySuffix /> }
							onChange={ ( value?: string ) => setPreview( { subtotal: toAmount( value ) } ) }
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Hide dimmed blocks', 'yaycheckout' ) }
							help={ __( 'Shows the layout without them. Select them from List View.', 'yaycheckout' ) }
							checked={ preview.collapse }
							onChange={ ( checked: boolean ) => setPreview( { collapse: checked } ) }
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

/**
 * A copied block keeps its original's blockId, and neither the server nor the script can tell them apart.
 * The block that held the id before keeps it, so "block" rules keep pointing at the original; every other
 * holder gets a new one.
 */
function BlockIdKeeper() {
	const holders = useSelect( ( select ) => {
		const { getBlockAttributes, getClientIdsWithDescendants } = select( blockEditorStore );

		return JSON.stringify(
			( getClientIdsWithDescendants() as string[] ).flatMap( ( id ) => {
				const blockId = getBlockAttributes( id )?.blockId;

				return typeof blockId === 'string' && blockId !== '' ? [ [ id, blockId ] ] : [];
			} )
		);
	}, [] );
	const { updateBlockAttributes } = useDispatch( blockEditorStore );
	const previous = useRef( new Map< string, string >() );

	useEffect( () => {
		const pairs = JSON.parse( holders ) as [ string, string ][];
		const byId = new Map< string, string[] >();

		pairs.forEach( ( [ clientId, blockId ] ) => byId.set( blockId, [ ...( byId.get( blockId ) ?? [] ), clientId ] ) );

		byId.forEach( ( clientIds, blockId ) => {
			if ( clientIds.length < 2 ) {
				return;
			}

			const before = previous.current.get( blockId );
			const keeper = before && clientIds.includes( before ) ? before : clientIds[ 0 ];

			clientIds
				.filter( ( clientId ) => clientId !== keeper )
				.forEach( ( clientId ) => updateBlockAttributes( clientId, { blockId: newBlockId() } ) );
		} );

		previous.current = new Map(
			[ ...byId ].map( ( [ blockId, clientIds ] ) => {
				const before = previous.current.get( blockId );

				return [ blockId, before && clientIds.includes( before ) ? before : clientIds[ 0 ] ];
			} )
		);
	}, [ holders ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return null;
}

registerPlugin( 'yaycheckout-block-id-keeper', {
	render: function BlockIds() {
		return useIsLayoutEditor() ? <BlockIdKeeper /> : null;
	},
} );
