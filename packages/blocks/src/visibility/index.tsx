/**
 * The block visibility setting in the editor. BlockRegistry enqueues it in every editor, so markup saved
 * in a layout stays valid anywhere; the Visibility panel and the preview sidebar show in the layout editor only.
 */
import { InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import {
	Button,
	Flex,
	FlexBlock,
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
	type VisibilityRule,
	type VisibilitySetting,
	type VisibilityState,
} from '../shared/visibility-rules';
import stepMeta from '../step/block.json';
import './editor.css';

type RuleType = VisibilityRule[ 'type' ];

/** A rule as the panel edits it: the stored shape, which normalize() may still reject. */
type DraftRule = { type: RuleType; step?: number | 'last'; op?: 'gte' | 'lt'; amount?: number };

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
	};
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

/** The store's currency symbol as an input's suffix. */
function CurrencySuffix() {
	return <InputControlSuffixWrapper>{ editorData.currencySymbol }</InputControlSuffixWrapper>;
}

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

function VisibilityPanel( { attributes, setAttributes }: LayoutPanelProps ) {
	const draft = draftOf( attributes[ ATTRIBUTE ] );
	const steps = useStepOptions();

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
							<Fragment key={ rule.type }>
								{ index > 0 && <p className="yaycheckout-visibility__and">{ __( 'and', 'yaycheckout' ) }</p> }
								<RuleRow
									rule={ rule }
									used={ used }
									steps={ steps }
									onChange={ ( next ) => write( { ...draft, rules: rules.map( ( kept, at ) => ( at === index ? next : kept ) ) } ) }
									onRemove={ () => write( { ...draft, rules: rules.filter( ( _, at ) => at !== index ) } ) }
								/>
							</Fragment>
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
 * Whether the preview inputs would hide a block with this setting. With Step off, step conditions are left
 * out, so the preview shows what the other inputs alone decide.
 */
function previewHides( setting: VisibilitySetting, preview: Preview, stepCount: number ): boolean {
	if ( preview.step === 'off' ) {
		const rules = setting.rules.filter( ( rule ) => rule.type !== 'step' );

		return rules.length > 0 && ! isVisible( { ...setting, rules }, preview, 0, stepCount );
	}

	return ! isVisible( setting, preview, preview.step === 'last' ? stepCount : preview.step, stepCount );
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
							{ __( 'Dims the blocks these inputs would hide on the checkout. Nothing here is saved.', 'yaycheckout' ) }
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
