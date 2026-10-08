import { decodeEntities } from '@wordpress/html-entities';
import { InspectorControls, RichText, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './editor.css';

interface FieldValueAttributes {
	fieldKey: string;
	placeholder: string;
	showLabel: boolean;
}

interface EditProps {
	attributes: FieldValueAttributes;
	setAttributes: ( attributes: Partial< FieldValueAttributes > ) => void;
}

interface LayoutField {
	key: string;
	label: string;
}

/** Mirrors render.php: a checkbox's value is always 1, a password must never be echoed, a hidden field holds nothing typed. */
const UNSHOWABLE_TYPES = [ 'checkbox', 'password', 'hidden' ];

/**
 * The layout's address fields with the label each prints. Mirrors LayoutMarkupReader::fields():
 * Email address is billing_email, each Field block with a Key is its own, a later block with the
 * same Key wins. An empty label falls back as email/render.php and field/render.php print it.
 */
function layoutFields(
	clientIds: string[],
	getBlockName: ( clientId: string ) => string | null,
	getBlockAttributes: ( clientId: string ) => Record< string, unknown > | null
): LayoutField[] {
	const fields = new Map< string, string >();

	for ( const clientId of clientIds ) {
		const name = getBlockName( clientId );
		const attributes = getBlockAttributes( clientId ) ?? {};
		// Tags stripped before entities are decoded, as LayoutMarkupReader::plain_text() does.
		const label = decodeEntities( String( attributes.label ?? '' ).replace( /<[^>]*>/g, '' ) ).trim();

		if ( name === 'yaycheckout/email' ) {
			fields.set( 'billing_email', label || __( 'Email address', 'yaycheckout' ) );
		} else if ( name === 'yaycheckout/field' ) {
			const key = String( attributes.fieldKey ?? '' ).trim();

			if ( key !== '' && ! UNSHOWABLE_TYPES.includes( String( attributes.type ?? 'text' ) ) ) {
				fields.set( key, label || key );
			}
		}
	}

	return [ ...fields ].map( ( [ key, fieldLabel ] ) => ( { key, label: fieldLabel } ) );
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { fieldKey, placeholder, showLabel } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-field-value' } );
	const fields = useSelect( ( select ) => {
		const { getClientIdsWithDescendants, getBlockName, getBlockAttributes } = select( blockEditorStore );

		return layoutFields( getClientIdsWithDescendants(), getBlockName, getBlockAttributes );
	}, [] );
	const field = fields.find( ( candidate ) => candidate.key === fieldKey );
	const { __unstableMarkNextChangeAsNotPersistent: markNotPersistent } = useDispatch( blockEditorStore );

	// The field left the layout: point at nothing, so the storefront prints nothing.
	useEffect( () => {
		if ( fieldKey !== '' && ! field ) {
			markNotPersistent();
			setAttributes( { fieldKey: '', placeholder: '' } );
		}
	}, [ fieldKey, field ] );

	const groups = [
		{ label: __( 'Billing', 'yaycheckout' ), prefix: 'billing_' },
		{ label: __( 'Shipping', 'yaycheckout' ), prefix: 'shipping_' },
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field value', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Field', 'yaycheckout' ) }
						value={ field ? fieldKey : '' }
						// A placeholder is written for one field ("email@____"), so a new field starts from its own name.
						onChange={ ( value: string ) => setAttributes( { fieldKey: value, placeholder: '' } ) }
						help={ fields.length === 0 ? __( 'Add an Email address or Field block to the layout first.', 'yaycheckout' ) : undefined }
						__nextHasNoMarginBottom
					>
						<option value="">{ __( 'Select a field', 'yaycheckout' ) }</option>
						{ groups.map( ( group ) => {
							const options = fields.filter( ( candidate ) => candidate.key.startsWith( group.prefix ) );

							return (
								options.length > 0 && (
									<optgroup key={ group.prefix } label={ group.label }>
										{ options.map( ( option ) => (
											<option key={ option.key } value={ option.key }>
												{ option.label }
											</option>
										) ) }
									</optgroup>
								)
							);
						} ) }
					</SelectControl>
					<ToggleControl
						label={ __( 'Show label', 'yaycheckout' ) }
						help={ __( "Prints the field's label above its value.", 'yaycheckout' ) }
						checked={ showLabel }
						onChange={ ( value: boolean ) => setAttributes( { showLabel: value } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ field ? (
					<>
						{ showLabel && <div className="yaycheckout-field-value__label">{ field.label }</div> }
						{ /* The muted text the shopper sees until they fill the field; empty falls back to the field's label. */ }
						<RichText
							tagName="div"
							className="yaycheckout-field-value__value is-empty"
							value={ placeholder }
							onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
							placeholder={ field.label }
							allowedFormats={ [] }
							withoutInteractiveFormatting
						/>
					</>
				) : (
					<div className="yaycheckout-field-value__value is-empty">
						{ __( 'Select a field in the block settings. Nothing shows on the storefront until then.', 'yaycheckout' ) }
					</div>
				) }
			</div>
		</>
	);
}
