import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { CSSProperties } from 'react';
import { billingAddressFields, shippingAddressFields, type AddressFieldDef } from '../shared/address-fields';
import { FieldPreview } from '../shared/field-preview';
import { RequiredIndicatorControls } from '../shared/required-indicator-controls';

const ADDRESS_PARENTS = {
	'yaycheckout/billing': 'billing',
	'yaycheckout/shipping': 'shipping',
} as const;

interface EditorBlock {
	clientId: string;
	name: string;
	attributes?: Record< string, unknown >;
}

function catalogFor( section: 'billing' | 'shipping' ): AddressFieldDef[] {
	return section === 'shipping' ? shippingAddressFields() : billingAddressFields();
}

function collectFieldKeys(
	getBlocks: ( rootClientId?: string ) => EditorBlock[],
	rootClientId: string,
	excludeId: string
): string[] {
	const keys: string[] = [];

	for ( const block of getBlocks( rootClientId ) ) {
		if ( block.name === 'yaycheckout/field' && block.clientId !== excludeId ) {
			const key = block.attributes?.fieldKey;
			if ( typeof key === 'string' && key !== '' ) {
				keys.push( key );
			}
		}

		keys.push( ...collectFieldKeys( getBlocks, block.clientId, excludeId ) );
	}

	return keys;
}

interface FieldAttributes {
	fieldKey: string;
	type: string;
	label: string;
	placeholder: string;
	description: string;
	required: boolean;
	requiredMark: string;
	optionalText: string;
	autocomplete: string;
	validate: string[];
	width: string;
}

interface EditProps {
	attributes: FieldAttributes;
	setAttributes: ( attributes: Partial< FieldAttributes > ) => void;
	clientId: string;
}

/**
 * In billing/shipping the Woo key is chosen from a dropdown. Keys are unique
 * across the whole section, including fields nested in Rows/Columns.
 *
 * A field always has a key: without one it renders under a made-up name that
 * WooCommerce never validates or saves, so a keyless field takes the first free one.
 */
export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { fieldKey, type, label, placeholder, required, requiredMark = '', optionalText = '' } = attributes;
	const { addressSection, usedKeys } = useSelect(
		( select ) => {
			const { getBlockParents, getBlockName, getBlocks } = select( blockEditorStore );
			const parents = getBlockParents( clientId );
			const sectionId = [ ...parents ].reverse().find( ( id ) =>
				Object.prototype.hasOwnProperty.call( ADDRESS_PARENTS, getBlockName( id ) )
			);
			const sectionName = sectionId ? getBlockName( sectionId ) : '';
			const section =
				sectionName === 'yaycheckout/billing' || sectionName === 'yaycheckout/shipping'
					? ADDRESS_PARENTS[ sectionName ]
					: null;
			const taken = sectionId
				? collectFieldKeys(
						getBlocks as ( rootClientId?: string ) => EditorBlock[],
						sectionId,
						clientId
				  )
				: [];

			return { addressSection: section, usedKeys: taken };
		},
		[ clientId ]
	);
	const catalog = addressSection ? catalogFor( addressSection ) : [];
	const isEmail = type === 'email' || fieldKey === 'billing_email';
	const available = catalog.filter( ( field ) => field.key === fieldKey || ! usedKeys.includes( field.key ) );
	const { __unstableMarkNextChangeAsNotPersistent: markNotPersistent } = useDispatch( blockEditorStore );

	const pickField = ( def: AddressFieldDef ) =>
		setAttributes( { fieldKey: def.key, type: def.type, label: def.label, required: def.required } );

	useEffect( () => {
		if ( fieldKey === '' && available.length > 0 ) {
			// Part of inserting the block, not an edit the author should undo back to keyless.
			markNotPersistent();
			pickField( available[ 0 ] );
		}
	}, [ fieldKey, available[ 0 ]?.key ] );

	const keyOptions =
		available.length > 0
			? available.map( ( field ) => ( { label: `${ field.label } (${ field.key })`, value: field.key } ) )
			: [ { label: __( 'Every field in this section is already used', 'yaycheckout' ), value: '' } ];
	const blockProps = useBlockProps( { className: 'yaycheckout-field' } );
	const canvasLabel = label || fieldKey || __( 'Field', 'yaycheckout' );
	const labelStyle = {
		fontSize: 'inherit',
		fontWeight: 'inherit',
	} as CSSProperties;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field', 'yaycheckout' ) }>
					{ addressSection && (
						<SelectControl
							label={ __( 'WooCommerce field', 'yaycheckout' ) }
							value={ fieldKey }
							options={ keyOptions }
							onChange={ ( value ) => {
								const def = catalog.find( ( field ) => field.key === value );
								if ( def ) {
									pickField( def );
								}
							} }
						/>
					) }
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<RequiredIndicatorControls
						required={ isEmail || required }
						requiredMark={ requiredMark }
						optionalText={ optionalText }
						requiredLocked={ isEmail }
						requiredHelp={
							isEmail
								? __( 'Email is always required.', 'yaycheckout' )
								: undefined
						}
						onRequiredChange={ ( value ) => setAttributes( { required: value } ) }
						onRequiredMarkChange={ ( value ) => setAttributes( { requiredMark: value } ) }
						onOptionalTextChange={ ( value ) => setAttributes( { optionalText: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<FieldPreview
					label={ canvasLabel }
					type={ type }
					required={ isEmail || required }
					requiredMark={ requiredMark }
					optionalText={ optionalText }
					placeholder={ placeholder }
					labelStyle={ labelStyle }
				/>
			</div>
		</>
	);
}
