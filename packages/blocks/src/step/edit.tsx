import {
	InnerBlocks,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useBlocksAllowedExcept } from '../shared/allowed-blocks';
import { stepTitle } from '../shared/step-title';

const isSteps = ( type: { name: string } ) => type.name === 'yaycheckout/steps';

interface StepAttributes {
	title: string;
}

interface EditProps {
	attributes: StepAttributes;
	setAttributes: ( attributes: Partial< StepAttributes > ) => void;
	clientId: string;
}

export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { title } = attributes;
	const index = useSelect( ( select ) => select( blockEditorStore ).getBlockIndex( clientId ), [ clientId ] );
	const position = typeof index === 'number' && index >= 0 ? index + 1 : 1;
	const blockProps = useBlockProps( { className: 'yaycheckout-step' } );
	const allowedBlocks = useBlocksAllowedExcept( isSteps );
	const bodyProps = useInnerBlocksProps(
		{ className: 'yaycheckout-step__body' },
		{
			allowedBlocks,
			template: [ [ 'yaycheckout/step-button' ] ],
			templateLock: false,
			renderAppender: InnerBlocks.ButtonBlockAppender,
		}
	);

	return (
		<div { ...blockProps }>
			<div className="yaycheckout-step__header">
				<span className="yaycheckout-step__index">{ position }</span>
				<RichText
					tagName="span"
					className="yaycheckout-step__title"
					value={ title }
					onChange={ ( value: string ) => setAttributes( { title: value } ) }
					placeholder={ stepTitle( '' ) }
					allowedFormats={ [] }
					withoutInteractiveFormatting
				/>
			</div>
			<div { ...bodyProps } />
		</div>
	);
}
