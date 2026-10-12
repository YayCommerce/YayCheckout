import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { createInterpolateElement, type ReactNode } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';
import { stepTitle, useLayoutStepIds } from '../shared/step-title';
import './editor.css';

type Variant = 'circles' | 'dots' | 'segments' | 'bar' | 'text';

interface EditProps {
	attributes: { variant: Variant };
	setAttributes: ( attributes: { variant: Variant } ) => void;
}

function ProgressItem( { title, position }: { title: string; position: number } ) {
	const state = position === 1 ? 'current' : 'locked';

	return (
		<li
			className="yaycheckout-progress__item"
			data-state={ state }
			data-step={ position }
			aria-current={ state === 'current' ? 'step' : undefined }
		>
			<span className="yaycheckout-progress__marker">{ position }</span>
			<span className="yaycheckout-progress__title">{ stepTitle( decodeEntities( title ) ) }</span>
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

// Mirrors CheckoutProgress::summary(), with step 1 open.
function Summary( { total, withTrack }: { total: number; withTrack: boolean } ) {
	return (
		<div className="yaycheckout-progress__summary">
			{ withTrack && (
				<div className="yaycheckout-progress__track">
					<span className="yaycheckout-progress__fill" style={ { width: `${ ( 1 / Math.max( total, 1 ) ) * 100 }%` } } />
				</div>
			) }
			<p className="yaycheckout-progress__count">
				{ createInterpolateElement(
					/* translators: 1: number of the open step, 2: number of steps. */
					sprintf( __( 'Step %1$s of %2$s', 'yaycheckout' ), '<current />', String( total ) ),
					{ current: <span className="yaycheckout-progress__current">1</span> }
				) }
			</p>
		</div>
	);
}

function ProgressBody( { variant, total, children }: { variant: Variant; total: number; children: ReactNode } ) {
	return (
		<>
			{ ( variant === 'bar' || variant === 'text' ) && (
				<Summary total={ total } withTrack={ variant === 'bar' } />
			) }
			<ol className="yaycheckout-progress__list">{ children }</ol>
		</>
	);
}

// Mirrors CheckoutProgress::markup(): the same wrapper, list and items.
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const stepIds = useLayoutStepIds();
	// The inserter preview holds only this block, so it shows sample steps instead of the notice.
	const isPreview: boolean = useSelect(
		( select ) => select( blockEditorStore ).getSettings().isPreviewMode === true,
		[]
	);
	const blockProps = useBlockProps( {
		className: `yaycheckout-progress is-variant-${ attributes.variant }`,
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
					{ __( 'Add a Steps block to use Progress.', 'yaycheckout' ) }
				</Notice>
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Progress', 'yaycheckout' ) }>
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
