import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

interface SlotAttributes {
	slotName: string;
}

interface EditProps {
	attributes: SlotAttributes;
	setAttributes: ( attributes: Partial< SlotAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { slotName } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-slot' } );
	const caption = slotName
		? sprintf(
				/* translators: %s: slot name slug */
				__( 'Slot: %s', 'yaycheckout' ),
				slotName
		  )
		: __( 'Slot: unnamed', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Slot', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Slot name', 'yaycheckout' ) }
						value={ slotName }
						onChange={ ( value ) => setAttributes( { slotName: value } ) }
						help={ __( 'Snake_case slug other plugins hook into, e.g. after_contact.', 'yaycheckout' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div
				{ ...blockProps }
				style={ {
					minHeight: '48px',
					padding: '12px',
					border: '1px dashed #c3c4c7',
					color: '#646970',
					fontSize: '13px',
					textAlign: 'center',
				} }
			>
				{ caption }
			</div>
		</>
	);
}
