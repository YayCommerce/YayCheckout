import { TextControl } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

export interface FieldTextAttributes {
	label: string;
	placeholder: string;
	description: string;
}

interface FieldTextControlsProps extends FieldTextAttributes {
	setAttributes: ( attributes: Partial< FieldTextAttributes > ) => void;
	/** False when the label holds rich text, edited only on the canvas. */
	showLabel?: boolean;
	showPlaceholder?: boolean;
	showHelp?: boolean;
}

/** A field block's Label, Placeholder and Help text, as every field sidebar shows them. */
export function FieldTextControls( {
	label,
	placeholder,
	description,
	setAttributes,
	showLabel = true,
	showPlaceholder = true,
	showHelp = true,
}: FieldTextControlsProps ) {
	return (
		<>
			{ showLabel && (
				<TextControl
					label={ __( 'Label', 'yaycheckout' ) }
					value={ decodeEntities( label ) }
					onChange={ ( value ) => setAttributes( { label: value } ) }
				/>
			) }
			{ showPlaceholder && (
				<TextControl
					label={ __( 'Placeholder', 'yaycheckout' ) }
					value={ placeholder }
					onChange={ ( value ) => setAttributes( { placeholder: value } ) }
				/>
			) }
			{ showHelp && (
				<TextControl
					label={ __( 'Help text', 'yaycheckout' ) }
					help={ __( 'Shown under the field while the shopper fills it in.', 'yaycheckout' ) }
					value={ description }
					onChange={ ( value ) => setAttributes( { description: value } ) }
				/>
			) }
		</>
	);
}
