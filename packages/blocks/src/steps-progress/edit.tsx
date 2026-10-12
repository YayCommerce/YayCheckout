import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { createInterpolateElement, type ReactNode } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';
import { stepTitle } from '../shared/step-title';
import stepsMeta from '../steps/block.json';
import './editor.css';

const STEPS = stepsMeta.name;

type Variant = 'circles' | 'dots' | 'segments' | 'bar' | 'text';

// Mirrors CheckoutStepsProgress::markup(): bar and text show a summary and hide the list.
const hasSummary = ( variant: Variant ) => variant === 'bar' || variant === 'text';

interface Attributes {
	variant: Variant;
	clickable: boolean;
}

interface EditProps {
	attributes: Attributes;
	setAttributes: ( attributes: Partial< Attributes > ) => void;
}

function ProgressItem( { title, position }: { title: string; position: number } ) {
	const state = position === 1 ? 'current' : 'locked';

	return (
		<li
			className="yaycheckout-steps-progress__item"
			data-state={ state }
			data-step={ position }
			aria-current={ state === 'current' ? 'step' : undefined }
		>
			<span className="yaycheckout-steps-progress__marker">{ position }</span>
			<span className="yaycheckout-steps-progress__title">{ stepTitle( decodeEntities( title ) ) }</span>
		</li>
	);
}

function StepProgressItem( { stepId, position }: { stepId: string; position: number } ) {
	const title = useSelect(
		( select ) => {
			const value = select( blockEditorStore ).getBlockAttributes( stepId )?.title;

			return typeof value === 'string' ? value : '';
		},
		[ stepId ]
	);

	return <ProgressItem title={ title } position={ position } />;
}

// Mirrors CheckoutStepsProgress::summary(), with step 1 open.
function Summary( { total, withTrack }: { total: number; withTrack: boolean } ) {
	return (
		<div className="yaycheckout-steps-progress__summary">
			{ withTrack && (
				<div className="yaycheckout-steps-progress__track">
					<span className="yaycheckout-steps-progress__fill" style={ { width: `${ ( 1 / Math.max( total, 1 ) ) * 100 }%` } } />
				</div>
			) }
			<p className="yaycheckout-steps-progress__count">
				{ createInterpolateElement(
					/* translators: 1: number of the open step, 2: number of steps. */
					sprintf( __( 'Step %1$s of %2$s', 'yaycheckout' ), '<current />', String( total ) ),
					{ current: <span className="yaycheckout-steps-progress__current">1</span> }
				) }
			</p>
		</div>
	);
}

function ProgressBody( { variant, total, children }: { variant: Variant; total: number; children: ReactNode } ) {
	return (
		<>
			{ hasSummary( variant ) && (
				<Summary total={ total } withTrack={ variant === 'bar' } />
			) }
			<ol className="yaycheckout-steps-progress__list">{ children }</ol>
		</>
	);
}

// Mirrors CheckoutStepsProgress::markup(): the same wrapper, list and items.
export default function Edit( { attributes, setAttributes }: EditProps ) {
	// The store's own order array, so typing elsewhere doesn't re-render this block.
	const stepIds: string[] | null = useSelect(
		( select ) => {
			const { getBlockOrder, getBlocksByName } = select( blockEditorStore );

			// Mirrors CheckoutStepsProgress::titles_in(): the layout's first Steps block, in document order.
			const stepsId = ( getBlocksByName( STEPS ) as string[] )[ 0 ];

			return stepsId ? getBlockOrder( stepsId ) : null;
		},
		[]
	);
	// The inserter preview holds only this block, so it shows sample steps instead of the notice.
	const isPreview: boolean = useSelect(
		( select ) => select( blockEditorStore ).getSettings().isPreviewMode === true,
		[]
	);
	const blockProps = useBlockProps( {
		className: `yaycheckout-steps-progress is-variant-${ attributes.variant }`,
	} );

	if ( isPreview && stepIds === null ) {
		const titles = [
			__( 'Information', 'yaycheckout' ),
			__( 'Shipping', 'yaycheckout' ),
			__( 'Payment', 'yaycheckout' ),
		];

		return (
			<div { ...blockProps }>
				<ProgressBody variant={ attributes.variant } total={ titles.length }>
					{ titles.map( ( title, index ) => (
						<ProgressItem key={ title } title={ title } position={ index + 1 } />
					) ) }
				</ProgressBody>
			</div>
		);
	}

	// A Notice, not a Placeholder: Placeholder hides its instructions when the block is narrow.
	if ( stepIds === null ) {
		return (
			<div { ...blockProps }>
				<Notice status="warning" isDismissible={ false }>
					{ __( 'Add a Steps block to use Steps Progress.', 'yaycheckout' ) }
				</Notice>
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Steps Progress', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Style', 'yaycheckout' ) }
						value={ attributes.variant }
						options={ [
							{ label: __( 'Numbered circles', 'yaycheckout' ), value: 'circles' },
							{ label: __( 'Dots', 'yaycheckout' ), value: 'dots' },
							{ label: __( 'Segmented bar', 'yaycheckout' ), value: 'segments' },
							{ label: __( 'Progress bar', 'yaycheckout' ), value: 'bar' },
							{ label: __( 'Text only', 'yaycheckout' ), value: 'text' },
						] }
						onChange={ ( value: string ) => setAttributes( { variant: value as Variant } ) }
					/>
					{ ! hasSummary( attributes.variant ) && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Make progress steps clickable', 'yaycheckout' ) }
							help={ __( 'Shoppers can click a step they already reached to go back to it.', 'yaycheckout' ) }
							checked={ attributes.clickable }
							onChange={ ( clickable: boolean ) => setAttributes( { clickable } ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ProgressBody variant={ attributes.variant } total={ stepIds.length }>
					{ stepIds.map( ( stepId, index ) => (
						<StepProgressItem key={ stepId } stepId={ stepId } position={ index + 1 } />
					) ) }
				</ProgressBody>
			</div>
		</>
	);
}
