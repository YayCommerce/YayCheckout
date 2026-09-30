import { InnerBlocks, InspectorControls, useBlockProps, store as blockEditorStore } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { accordionStepSections } from './sections';

interface AccordionStepAttributes {
	title: string;
}

interface EditProps {
	attributes: AccordionStepAttributes;
	setAttributes: ( attributes: Partial< AccordionStepAttributes > ) => void;
	clientId: string;
}

export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { title } = attributes;
	const index = useSelect( ( select ) => select( blockEditorStore ).getBlockIndex( clientId ), [ clientId ] );
	const position = typeof index === 'number' && index >= 0 ? index + 1 : 1;
	const blockProps = useBlockProps( { className: 'yaycheckout-accordion-step' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Accordion step', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Title', 'yaycheckout' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="yaycheckout-accordion-step__header">
					<span className="yaycheckout-accordion-step__index">{ position }</span>
					<span className="yaycheckout-accordion-step__title">{ title || __( 'Step', 'yaycheckout' ) }</span>
				</div>
				<div className="yaycheckout-accordion-step__body">
					<InnerBlocks
						allowedBlocks={ accordionStepSections }
						template={ [ [ 'yaycheckout/step-button' ] ] }
						templateLock={ false }
					/>
				</div>
			</div>
		</>
	);
}
