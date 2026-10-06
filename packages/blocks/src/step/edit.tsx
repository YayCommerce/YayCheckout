import {
	RichText,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useBlocksAllowedExcept } from '../shared/allowed-blocks';
import { useContainerAppender } from '../shared/container-appender';
import { stepTitle } from '../shared/step-title';
import stepButtonMeta from '../step-button/block.json';
import stepsMeta from '../steps/block.json';

const isSteps = ( type: { name: string } ) => type.name === stepsMeta.name;

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
			template: [ [ stepButtonMeta.name ] ],
			templateLock: false,
			renderAppender: useContainerAppender( clientId ),
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
