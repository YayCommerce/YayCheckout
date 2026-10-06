import { RichText, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { stepTitle } from '../shared/step-title';
import checkoutMeta from '../checkout/block.json';
import stepsMeta from '../steps/block.json';

const CHECKOUT = checkoutMeta.name;
const STEPS = stepsMeta.name;

function ProgressItem( { stepId, position }: { stepId: string; position: number } ) {
	const title = useSelect(
		( select ) => {
			const value = select( blockEditorStore ).getBlockAttributes( stepId )?.title;

			return typeof value === 'string' ? value : '';
		},
		[ stepId ]
	);
	const { updateBlockAttributes } = useDispatch( blockEditorStore );
	const state = position === 1 ? 'current' : 'locked';

	return (
		<li
			className="yaycheckout-progress__item"
			data-state={ state }
			data-step={ position }
			aria-current={ state === 'current' ? 'step' : undefined }
		>
			<span className="yaycheckout-progress__marker">{ position }</span>
			<RichText
				tagName="span"
				className="yaycheckout-progress__title"
				allowedFormats={ [] }
				value={ title }
				placeholder={ stepTitle( '' ) }
				onChange={ ( value: string ) => updateBlockAttributes( stepId, { title: value } ) }
			/>
		</li>
	);
}

export default function Edit( { clientId }: { clientId: string } ) {
	// The store's own order array, so typing elsewhere doesn't re-render this block.
	const stepIds: string[] | null = useSelect(
		( select ) => {
			const { getBlockOrder, getBlockParentsByBlockName, getBlocksByName } = select( blockEditorStore );
			const checkoutId = getBlockParentsByBlockName( clientId, CHECKOUT )[ 0 ];

			if ( ! checkoutId ) {
				return null;
			}

			// Mirrors CheckoutProgress::titles_in(): the first Steps block inside this Checkout.
			const stepsId = ( getBlocksByName( STEPS ) as string[] ).find( ( id ) =>
				getBlockParentsByBlockName( id, CHECKOUT ).includes( checkoutId )
			);

			return stepsId ? getBlockOrder( stepsId ) : null;
		},
		[ clientId ]
	);
	const blockProps = useBlockProps( { className: 'yaycheckout-progress' } );

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
		<ol { ...blockProps }>
			{ stepIds.map( ( stepId, index ) => (
				<ProgressItem key={ stepId } stepId={ stepId } position={ index + 1 } />
			) ) }
		</ol>
	);
}
