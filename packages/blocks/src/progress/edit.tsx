import { RichText, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { stepTitle } from '../shared/step-title';

const CHECKOUT = 'yaycheckout/checkout';
const STEPS = 'yaycheckout/steps';
const STEP = 'yaycheckout/step';

interface EditorBlock {
	name: string;
	clientId: string;
	attributes: { title?: unknown };
}

interface StepRef {
	clientId: string;
	title: string;
}

interface ProgressView {
	missing: boolean;
	steps: StepRef[];
}

function firstSteps( blocks: EditorBlock[], childrenOf: ( clientId: string ) => EditorBlock[] ): EditorBlock | null {
	for ( const block of blocks ) {
		if ( block.name === STEPS ) {
			return block;
		}

		const nested = firstSteps( childrenOf( block.clientId ), childrenOf );

		if ( nested ) {
			return nested;
		}
	}

	return null;
}

export default function Edit( { clientId }: { clientId: string } ) {
	const view: ProgressView = useSelect(
		( select ) => {
			const { getBlockParentsByBlockName, getBlocks } = select( blockEditorStore );
			const checkoutId = getBlockParentsByBlockName( clientId, CHECKOUT )[ 0 ];

			if ( ! checkoutId ) {
				return { missing: true, steps: [] };
			}

			const childrenOf = ( id: string ) => getBlocks( id ) as EditorBlock[];
			const stepsBlock = firstSteps( childrenOf( checkoutId ), childrenOf );

			if ( ! stepsBlock ) {
				return { missing: true, steps: [] };
			}

			return {
				missing: false,
				steps: ( getBlocks( stepsBlock.clientId ) as EditorBlock[] )
					.filter( ( block ) => block.name === STEP )
					.map( ( block ) => ( {
						clientId: block.clientId,
						title: typeof block.attributes.title === 'string' ? block.attributes.title : '',
					} ) ),
			};
		},
		[ clientId ]
	);
	const { updateBlockAttributes } = useDispatch( blockEditorStore );
	const blockProps = useBlockProps( { className: 'yaycheckout-progress' } );

	if ( view.missing ) {
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
			{ view.steps.map( ( step, index ) => {
				const position = index + 1;
				const state = position === 1 ? 'current' : 'locked';

				return (
					<li
						key={ step.clientId }
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
							value={ step.title }
							placeholder={ stepTitle( '' ) }
							onChange={ ( value: string ) => updateBlockAttributes( step.clientId, { title: value } ) }
						/>
					</li>
				);
			} ) }
		</ol>
	);
}
