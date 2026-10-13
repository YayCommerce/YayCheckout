import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plainText } from '../shared/plain-text';

interface SavingsNoteAttributes {
	text: string;
}

interface EditProps {
	attributes: SavingsNoteAttributes;
	setAttributes: ( attributes: Partial< SavingsNoteAttributes > ) => void;
}

/** Mirrors SavingsNote::DEFAULT_TEXT in includes/Renderer/SavingsNote.php. */
const DEFAULT_TEXT = __( "You're saving %s", 'yaycheckout' );

const PREVIEW_AMOUNT = '$5.00';

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps();
	const raw = plainText( attributes.text );
	const template = raw.includes( '%s' ) ? raw : DEFAULT_TEXT;
	const preview = template.split( '%s' ).join( PREVIEW_AMOUNT );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Savings note', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Text', 'yaycheckout' ) }
						help={ __( 'Use %s where the amount goes.', 'yaycheckout' ) }
						value={ attributes.text }
						placeholder={ DEFAULT_TEXT }
						onChange={ ( value ) => setAttributes( { text: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>{ preview }</div>
		</>
	);
}
