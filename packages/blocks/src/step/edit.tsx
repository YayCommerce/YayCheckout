import {
	RichText,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { useBlocksAllowedExcept } from '../shared/allowed-blocks';
import { useBlockGapCSS, type BlockGapStyles } from '../shared/block-gap';
import { stepTitle } from '../shared/step-title';
import stepButtonMeta from '../step-button/block.json';
import stepsMeta from '../steps/block.json';
import './editor.css';

const isSteps = ( type: { name: string } ) => type.name === stepsMeta.name;

interface StepAttributes {
	title: string;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: StepAttributes;
	setAttributes: ( attributes: Partial< StepAttributes > ) => void;
	clientId: string;
	context: { 'yaycheckout/showHeadings'?: boolean };
}

export default function Edit( { attributes, setAttributes, clientId, context }: EditProps ) {
	const { title, style } = attributes;
	// The storefront hides the header; here it stays, faded, so the title Progress shows can still be edited.
	const hiddenOnCheckout = context[ 'yaycheckout/showHeadings' ] === false;
	const index = useSelect( ( select ) => select( blockEditorStore ).getBlockIndex( clientId ), [ clientId ] );
	const position = typeof index === 'number' && index >= 0 ? index + 1 : 1;
	const css = useBlockGapCSS( `#block-${ clientId } > .yaycheckout-step__body`, style );
	const blockProps = useBlockProps( { className: 'yaycheckout-step' } );
	const allowedBlocks = useBlocksAllowedExcept( isSteps );
	const bodyProps = useInnerBlocksProps(
		{ className: 'yaycheckout-step__body' },
		{
			allowedBlocks,
			template: [ [ stepButtonMeta.name ] ],
			templateLock: false,
		}
	);

	return (
		<div { ...blockProps }>
			{ css && <style>{ css }</style> }
			<div className={ hiddenOnCheckout ? 'yaycheckout-step__header is-hidden-on-checkout' : 'yaycheckout-step__header' }>
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
				{ hiddenOnCheckout && (
					<span className="yaycheckout-step__hidden-note">{ __( 'Hidden on the checkout', 'yaycheckout' ) }</span>
				) }
			</div>
			<div { ...bodyProps } />
		</div>
	);
}
