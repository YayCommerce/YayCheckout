import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { Button, Notice, PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { extraFieldKeys } from '../shared/extra-field-keys';
import { FieldPreview, type FieldOption } from '../shared/field-preview';

const TYPE_OPTIONS = [
	{ label: __( 'Text', 'yaycheckout' ), value: 'text' },
	{ label: __( 'Select', 'yaycheckout' ), value: 'select' },
	{ label: __( 'Radio', 'yaycheckout' ), value: 'radio' },
	{ label: __( 'Checkbox', 'yaycheckout' ), value: 'checkbox' },
	{ label: __( 'Date / Time', 'yaycheckout' ), value: 'datetime' },
];

const CHOICE_TYPES = [ 'select', 'radio', 'checkbox' ];

interface ExtraFieldAttributes {
	fieldKey: string;
	label: string;
	placeholder: string;
	required: boolean;
	type: string;
	options: FieldOption[];
}

interface EditProps {
	attributes: ExtraFieldAttributes;
	setAttributes: ( attributes: Partial< ExtraFieldAttributes > ) => void;
}

function emptyOption(): FieldOption {
	return { label: '', value: '' };
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { fieldKey, label, placeholder, required, type, options } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-extra-field' } );
	const canvasLabel = label || fieldKey || __( 'Extra field', 'yaycheckout' );
	const showOptions = CHOICE_TYPES.includes( type );
	const trimmedKey = fieldKey.trim();
	const isDuplicate = useSelect(
		( select ) =>
			trimmedKey !== '' &&
			extraFieldKeys( select( blockEditorStore ) ).filter( ( key ) => key === trimmedKey ).length > 1,
		[ trimmedKey ]
	);
	let keyError = '';

	if ( trimmedKey === '' ) {
		keyError = __( 'Key is required.', 'yaycheckout' );
	} else if ( isDuplicate ) {
		keyError = __( 'Another extra field already uses this Key.', 'yaycheckout' );
	}

	const updateOption = ( index: number, patch: Partial< FieldOption > ) => {
		const next = options.map( ( option, i ) => ( i === index ? { ...option, ...patch } : option ) );
		setAttributes( { options: next } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Extra field', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Key', 'yaycheckout' ) }
						value={ fieldKey }
						onChange={ ( value ) => setAttributes( { fieldKey: value } ) }
						help={ __( 'Posted name stored on the order, e.g. vat_number.', 'yaycheckout' ) }
					/>
					{ keyError !== '' && (
						<Notice status="error" isDismissible={ false }>
							{ keyError }
						</Notice>
					) }
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'yaycheckout' ) }
						value={ placeholder }
						onChange={ ( value ) => setAttributes( { placeholder: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'yaycheckout' ) }
						checked={ required }
						onChange={ ( value ) => setAttributes( { required: value } ) }
					/>
					<SelectControl
						label={ __( 'Type', 'yaycheckout' ) }
						value={ type }
						options={ TYPE_OPTIONS }
						onChange={ ( value ) => setAttributes( { type: value } ) }
					/>
					{ showOptions && (
						<>
							<p style={ { fontWeight: 600, marginBottom: '8px' } }>
								{ __( 'Options', 'yaycheckout' ) }
							</p>
							{ options.map( ( option, index ) => (
								<div
									key={ index }
									style={ { display: 'flex', gap: '8px', marginBottom: '8px', alignItems: 'flex-end' } }
								>
									<TextControl
										label={ index === 0 ? __( 'Label', 'yaycheckout' ) : '' }
										value={ option.label }
										onChange={ ( value ) => updateOption( index, { label: value } ) }
									/>
									<TextControl
										label={ index === 0 ? __( 'Value', 'yaycheckout' ) : '' }
										value={ option.value }
										onChange={ ( value ) => updateOption( index, { value: value } ) }
									/>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () =>
											setAttributes( { options: options.filter( ( _, i ) => i !== index ) } )
										}
									>
										{ __( 'Remove', 'yaycheckout' ) }
									</Button>
								</div>
							) ) }
							<Button
								variant="secondary"
								onClick={ () => setAttributes( { options: [ ...options, emptyOption() ] } ) }
							>
								{ __( 'Add option', 'yaycheckout' ) }
							</Button>
						</>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<FieldPreview
					label={ canvasLabel }
					type={ type }
					required={ required }
					placeholder={ placeholder }
					options={ options }
				/>
			</div>
		</>
	);
}
