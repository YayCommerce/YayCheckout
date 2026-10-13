import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { BaseControl, Button, Flex, FlexBlock, Notice, PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { EXTRA_FIELD_KEY_CONTROL_CLASS, EXTRA_FIELD_KEY_PATTERN, extraFieldKeys } from '../shared/extra-field-keys';
import { FieldTextControls } from '../shared/field-text-controls';
import { FieldPreview, type FieldOption } from '../shared/field-preview';
import { RequiredIndicatorControls } from '../shared/required-indicator-controls';
import { DisclosureControls, DisclosurePreview, disclosureMode, type DisclosureMode } from '../shared/disclosure';
import { EXTRA_FIELD_LABEL } from './defaults';
import './editor.css';

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
	description: string;
	required: boolean;
	requiredMark: string;
	optionalText: string;
	type: string;
	options: FieldOption[];
	disclosure: DisclosureMode;
	disclosureText: string;
	rememberValue: boolean;
}

interface EditProps {
	attributes: ExtraFieldAttributes;
	setAttributes: ( attributes: Partial< ExtraFieldAttributes > ) => void;
}

function emptyOption(): FieldOption {
	return { label: '', value: '' };
}

/** A Key that passes EXTRA_FIELD_KEY_PATTERN, e.g. field_k3x9qa. */
function randomFieldKey(): string {
	return `field_${ Math.random().toString( 36 ).slice( 2, 8 ).padEnd( 6, '0' ) }`;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const {
		fieldKey,
		label,
		placeholder,
		description = '',
		required,
		requiredMark = '',
		optionalText = '',
		type,
		options,
		disclosure = 'none',
		disclosureText = '',
		rememberValue = false,
	} = attributes;
	const { __unstableMarkNextChangeAsNotPersistent: markNotPersistent } = useDispatch( blockEditorStore );

	// A block with neither Key nor Label was just inserted: give it both, so it is publishable at once.
	useEffect( () => {
		if ( fieldKey === '' && label === '' ) {
			// Part of inserting the block, not an edit the author should undo back to keyless.
			markNotPersistent();
			setAttributes( { fieldKey: randomFieldKey(), label: EXTRA_FIELD_LABEL } );
		}
	}, [] );

	const blockProps = useBlockProps( { className: 'yaycheckout-extra-field' } );
	const labelPlaceholder = fieldKey || EXTRA_FIELD_LABEL;
	const mode = disclosureMode( fieldKey.trim(), required, disclosure );
	// render.php names an unlabelled extra field "Extra field", so its link or header does too.
	const disclosureLabel = label || EXTRA_FIELD_LABEL;
	const showOptions = CHOICE_TYPES.includes( type );
	const trimmedKey = fieldKey.trim();
	const isDuplicate = useSelect(
		( select ) =>
			trimmedKey !== '' &&
			extraFieldKeys( select( blockEditorStore ) ).filter( ( key ) => key === trimmedKey ).length > 1,
		[ trimmedKey ]
	);
	// LayoutMarkupReader::publish_error() owns these Key rules; change them together.
	let keyError = '';

	if ( trimmedKey === '' ) {
		keyError = __( 'Key is required.', 'yaycheckout' );
	} else if ( ! EXTRA_FIELD_KEY_PATTERN.test( trimmedKey ) ) {
		keyError = __( 'Use only letters, numbers and underscores, not starting with an underscore.', 'yaycheckout' );
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
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					{ keyError !== '' && (
						<Notice status="error" isDismissible={ false } className="yaycheckout-extra-field__key-error">
							{ keyError }
						</Notice>
					) }
					<TextControl
						className={ EXTRA_FIELD_KEY_CONTROL_CLASS }
						label={ __( 'Key', 'yaycheckout' ) }
						value={ fieldKey }
						onChange={ ( value ) => setAttributes( { fieldKey: value } ) }
						help={ __( 'Posted name stored on the order, e.g. vat_number.', 'yaycheckout' ) }
					/>
					<FieldTextControls
						label={ label }
						placeholder={ placeholder }
						description={ description }
						setAttributes={ setAttributes }
						showPlaceholder={ type !== 'datetime' }
					/>
					<RequiredIndicatorControls
						required={ required }
						requiredMark={ requiredMark }
						optionalText={ optionalText }
						onRequiredChange={ ( value ) => setAttributes( { required: value } ) }
						onRequiredMarkChange={ ( value ) => setAttributes( { requiredMark: value } ) }
						onOptionalTextChange={ ( value ) => setAttributes( { optionalText: value } ) }
					/>
					<DisclosureControls
						fieldKey={ fieldKey.trim() }
						label={ disclosureLabel }
						required={ required }
						disclosure={ disclosure }
						disclosureText={ disclosureText }
						setAttributes={ setAttributes }
					/>
					<ToggleControl
						label={ __( 'Remember value', 'yaycheckout' ) }
						help={ __( 'Keeps what the shopper entered if they reload the checkout. Cleared once the order is placed.', 'yaycheckout' ) }
						checked={ rememberValue }
						onChange={ ( value ) => setAttributes( { rememberValue: value } ) }
					/>
					<SelectControl
						label={ __( 'Type', 'yaycheckout' ) }
						value={ type }
						options={ TYPE_OPTIONS }
						onChange={ ( value ) => setAttributes( { type: value } ) }
					/>
					{ showOptions && (
						<>
							<BaseControl.VisualLabel>{ __( 'Options', 'yaycheckout' ) }</BaseControl.VisualLabel>
							{ options.map( ( option, index ) => (
								<Flex key={ index } align="flex-end" gap={ 2 }>
									<FlexBlock>
										<TextControl
											label={ index === 0 ? __( 'Label', 'yaycheckout' ) : '' }
											value={ option.label }
											onChange={ ( value ) => updateOption( index, { label: value } ) }
										/>
									</FlexBlock>
									<FlexBlock>
										<TextControl
											label={ index === 0 ? __( 'Value', 'yaycheckout' ) : '' }
											value={ option.value }
											onChange={ ( value ) => updateOption( index, { value: value } ) }
										/>
									</FlexBlock>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () =>
											setAttributes( { options: options.filter( ( _, i ) => i !== index ) } )
										}
									>
										{ __( 'Remove', 'yaycheckout' ) }
									</Button>
								</Flex>
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
				<DisclosurePreview mode={ mode } fieldKey={ fieldKey.trim() } label={ disclosureLabel } disclosureText={ disclosureText }>
					<FieldPreview
						label={ label }
						onLabelChange={ ( value ) => setAttributes( { label: value } ) }
						labelPlaceholder={ labelPlaceholder }
						type={ type }
						required={ required }
						requiredMark={ requiredMark }
						optionalText={ optionalText }
						placeholder={ placeholder }
						description={ description }
						options={ options }
						inheritLabelFont
					/>
				</DisclosurePreview>
			</div>
		</>
	);
}
