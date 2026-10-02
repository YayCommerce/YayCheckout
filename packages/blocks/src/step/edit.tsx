import { InnerBlocks, InspectorControls, useBlockProps, store as blockEditorStore } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

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

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Step', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Title', 'yaycheckout' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="yaycheckout-step__header">
					<span className="yaycheckout-step__index">{ position }</span>
					<span className="yaycheckout-step__title">{ title || __( 'Step', 'yaycheckout' ) }</span>
				</div>
				<div className="yaycheckout-step__body">
					<InnerBlocks
						template={ [ [ 'yaycheckout/step-button' ] ] }
						templateLock={ false }
					/>
				</div>
			</div>
		</>
	);
}
