import { decodeEntities } from '@wordpress/html-entities';
import { InspectorControls, RichText, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { FormTokenField, PanelBody, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './editor.css';

interface FieldValueAttributes {
	fieldKeys: string[];
	separator: string;
	placeholder: string;
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

/**
 * The name each field goes by in the Fields picker: its label and section, so Billing and Shipping
 * "First name" stay apart. A name two fields still share gets the key, so every name maps back to one field.
 */
function tokenNames( fields: LayoutField[] ): Map< string, string > {
	const named = fields.map( ( field ) => {
		const section = field.key.startsWith( 'shipping_' ) ? __( 'Shipping', 'yaycheckout' ) : __( 'Billing', 'yaycheckout' );

		return { key: field.key, name: `${ field.label } (${ section })` };
	} );

	return new Map(
		named.map( ( { key, name } ) => [
			key,
			named.filter( ( other ) => other.name === name ).length > 1 ? `${ name } [${ key }]` : name,
		] )
	);
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { fieldKeys, separator, placeholder } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-field-value' } );
	const fields = useSelect( ( select ) => {
		const { getClientIdsWithDescendants, getBlockName, getBlockAttributes } = select( blockEditorStore );

		return layoutFields( getClientIdsWithDescendants(), getBlockName, getBlockAttributes );
	}, [] );
	const picked = fieldKeys
		.map( ( key ) => fields.find( ( candidate ) => candidate.key === key ) )
		.filter( ( field ): field is LayoutField => field !== undefined );
	const { __unstableMarkNextChangeAsNotPersistent: markNotPersistent } = useDispatch( blockEditorStore );

	// A field left the layout: drop it, so the storefront never reads a field the layout lost.
	useEffect( () => {
		if ( picked.length !== fieldKeys.length ) {
			markNotPersistent();
			setAttributes( {
				fieldKeys: picked.map( ( field ) => field.key ),
				...( picked.length === 0 ? { placeholder: '' } : {} ),
			} );
		}
	}, [ fieldKeys, picked.length ] );

	const names = tokenNames( fields );
	const keyOf = new Map( [ ...names ].map( ( [ key, name ] ) => [ name, key ] ) );
	const labels = picked.map( ( field ) => field.label ).join( separator );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field value', 'yaycheckout' ) }>
					{ /* Values print in the order the fields were added; remove and re-add one to move it. */ }
					<FormTokenField
						label={ __( 'Fields', 'yaycheckout' ) }
						value={ picked.map( ( field ) => names.get( field.key ) ?? field.key ) }
						suggestions={ [ ...names.values() ] }
						// A placeholder is written for its fields ("email@____"), so a new set starts from their names.
						onChange={ ( tokens ) =>
							setAttributes( {
								fieldKeys: tokens
									.map( ( token ) => keyOf.get( typeof token === 'string' ? token : token.value ) )
									.filter( ( key ): key is string => key !== undefined ),
								placeholder: '',
							} )
						}
						__experimentalValidateInput={ ( token: string ) => keyOf.has( token ) }
						__experimentalExpandOnFocus
						__experimentalShowHowTo={ false }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					{ fields.length === 0 && (
						<p className="components-base-control__help">
							{ __( 'Add an Email address or Field block to the layout first.', 'yaycheckout' ) }
						</p>
					) }
					{ fieldKeys.length > 1 && (
						<TextControl
							label={ __( 'Separator', 'yaycheckout' ) }
							help={ __( 'Printed between the values. Spaces count.', 'yaycheckout' ) }
							value={ separator }
							onChange={ ( value: string ) => setAttributes( { separator: value } ) }
							__nextHasNoMarginBottom
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ picked.length > 0 ? (
					<>
						{ /* The muted text the shopper sees until they fill a field; empty falls back to the fields' labels. */ }
						<RichText
							tagName="span"
							className="yaycheckout-field-value__value is-empty"
							value={ placeholder }
							onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
							placeholder={ labels }
							allowedFormats={ [] }
							withoutInteractiveFormatting
						/>
					</>
				) : (
					<span className="yaycheckout-field-value__value is-empty">
						{ __( 'Select a field in the block settings. Nothing shows on the storefront until then.', 'yaycheckout' ) }
					</span>
				) }
			</div>
		</>
	);
}
