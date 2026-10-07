import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { createInterpolateElement } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';
import { stepTitle } from '../shared/step-title';
import stepsMeta from '../steps/block.json';
import './editor.css';

const STEPS = stepsMeta.name;

type Variant = 'circles' | 'dots' | 'segments' | 'bar' | 'text';

interface EditProps {
	attributes: { variant: Variant };
	setAttributes: ( attributes: { variant: Variant } ) => void;
}

function ProgressItem( { stepId, position }: { stepId: string; position: number } ) {
	const title = useSelect(
		( select ) => {
			const value = select( blockEditorStore ).getBlockAttributes( stepId )?.title;

			return typeof value === 'string' ? value : '';
		},
		[ stepId ]
	);
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

// Mirrors CheckoutProgress::markup(): the same wrapper, list and items.
export default function Edit( { attributes, setAttributes }: EditProps ) {
	// The store's own order array, so typing elsewhere doesn't re-render this block.
	const stepIds: string[] | null = useSelect(
		( select ) => {
			const { getBlockOrder, getBlocksByName } = select( blockEditorStore );

			// Mirrors CheckoutProgress::titles_in(): the layout's first Steps block, in document order.
			const stepsId = ( getBlocksByName( STEPS ) as string[] )[ 0 ];

			return stepsId ? getBlockOrder( stepsId ) : null;
		},
		[]
	);
	const blockProps = useBlockProps( {
		className: `yaycheckout-progress is-variant-${ attributes.variant }`,
	} );

	if ( stepIds === null ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					label={ __( 'Progress', 'yaycheckout' ) }
					instructions={ __( 'Add a Steps block to use Progress.', 'yaycheckout' ) }
				/>
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
				{ ( attributes.variant === 'bar' || attributes.variant === 'text' ) && (
					<Summary total={ stepIds.length } withTrack={ attributes.variant === 'bar' } />
				) }
				<ol className="yaycheckout-progress__list">
					{ stepIds.map( ( stepId, index ) => (
						<ProgressItem key={ stepId } stepId={ stepId } position={ index + 1 } />
					) ) }
				</ol>
			</div>
		</>
	);
}
