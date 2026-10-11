import { InspectorControls, RichText, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { FormTokenField, PanelBody, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { EXTRA_FIELD_BLOCK, EXTRA_FIELD_KEY_PATTERN } from '../shared/extra-field-keys';
import { plainText } from '../shared/plain-text';
import { type BlockEditorSelectors, sectionFields } from '../shared/section-fields';
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
	section: 'billing' | 'shipping' | 'extra';
}

/** Mirrors render.php: a checkbox's value is always 1, a password must never be echoed, a hidden field holds nothing typed. */
const UNSHOWABLE_TYPES = [ 'checkbox', 'password', 'hidden' ];

/** Field blocks live only inside these, and take their section from the one they sit in. */
const ADDRESS_SECTIONS = [
	[ 'yaycheckout/billing', 'billing' ],
	[ 'yaycheckout/shipping', 'shipping' ],
] as const;

/**
 * The layout's fields with the label each prints: Email address (billing_email), the Field blocks
 * inside Billing and inside Shipping, then the Extra fields with a valid Key. A later block with the
 * same Key wins, as LayoutMarkupReader::fields() and ::extra_fields() read them. An empty label falls
 * back as email/render.php, field/render.php and extra-field/render.php print it. Fields with nothing
 * to show are left out, as render.php does.
 */
function layoutFields( editor: BlockEditorSelectors ): LayoutField[] {
	const fields = new Map< string, LayoutField >();
	const attributesOf = ( clientId: string ) => editor.getBlockAttributes( clientId ) ?? {};

	editor.getBlocksByName( 'yaycheckout/email' ).forEach( ( clientId ) => {
		const label = plainText( attributesOf( clientId ).label ) || __( 'Email address', 'yaycheckout' );

		fields.set( 'billing_email', { key: 'billing_email', label, section: 'billing' } );
	} );

	ADDRESS_SECTIONS.forEach( ( [ blockName, section ] ) => {
		sectionFields( editor, blockName ).forEach( ( { key, attributes } ) => {
			if ( ! UNSHOWABLE_TYPES.includes( String( attributes.type ?? 'text' ) ) ) {
				fields.set( key, { key, label: plainText( attributes.label ) || key, section } );
			}
		} );
	} );

	editor.getBlocksByName( EXTRA_FIELD_BLOCK ).forEach( ( clientId ) => {
		const attributes = attributesOf( clientId );
		const key = String( attributes.fieldKey ?? '' ).trim();
		// A single checkbox's value is always 1.
		const singleCheckbox = attributes.type === 'checkbox' && ! ( Array.isArray( attributes.options ) && attributes.options.length > 0 );

		// An address field with the same Key keeps it, as render.php does.
		if ( EXTRA_FIELD_KEY_PATTERN.test( key ) && ! singleCheckbox && ! fields.has( key ) ) {
			fields.set( key, { key, label: plainText( attributes.label ) || __( 'Extra field', 'yaycheckout' ), section: 'extra' } );
		}
	} );

	return [ ...fields.values() ];
}

const SECTIONS = {
	billing: __( 'Billing', 'yaycheckout' ),
	shipping: __( 'Shipping', 'yaycheckout' ),
	extra: __( 'Extra', 'yaycheckout' ),
};

/**
 * The name each field goes by in the Fields picker: its label and section, so Billing and Shipping
 * "First name" stay apart. A name two fields still share gets the key, so every name maps back to one field.
 */
function tokenNames( fields: LayoutField[] ): Map< string, string > {
	const named = fields.map( ( field ) => {
		return { key: field.key, name: `${ field.label } (${ SECTIONS[ field.section ] })` };
	} );

	const counts = new Map< string, number >();

	named.forEach( ( { name } ) => counts.set( name, ( counts.get( name ) ?? 0 ) + 1 ) );

	return new Map( named.map( ( { key, name } ) => [ key, ( counts.get( name ) ?? 0 ) > 1 ? `${ name } [${ key }]` : name ] ) );
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { fieldKeys, separator, placeholder } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-field-value' } );
	// A string, so the block re-renders only when the layout's fields change, not on every store change.
	const fieldsJson = useSelect(
		( select ) => JSON.stringify( layoutFields( select( blockEditorStore ) as BlockEditorSelectors ) ),
		[]
	);
	const fields = useMemo( (): LayoutField[] => JSON.parse( fieldsJson ), [ fieldsJson ] );
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

	const names = useMemo( () => tokenNames( fields ), [ fields ] );
	const keyOf = useMemo( () => new Map( [ ...names ].map( ( [ key, name ] ) => [ name, key ] ) ), [ names ] );
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
							{ __( 'Add an Email address, Field or Extra Field block to the layout first.', 'yaycheckout' ) }
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
				{ /* The muted text the shopper sees until they fill a field; empty falls back to the fields' labels. */ }
				{ picked.length > 0 ? (
					<RichText
						tagName="span"
						className="yaycheckout-field-value__value is-empty"
						value={ placeholder }
						onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
						placeholder={ labels }
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
				) : (
					<span className="yaycheckout-field-value__value is-empty">
						{ __( 'Select a field in the block settings. Nothing shows on the storefront until then.', 'yaycheckout' ) }
					</span>
				) }
			</div>
		</>
	);
}
