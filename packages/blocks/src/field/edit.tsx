import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import type { CSSProperties } from 'react';
import { billingAddressFields, shippingAddressFields, type AddressFieldDef } from '../shared/address-fields';
import { FieldPreview } from '../shared/field-preview';

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
 */
export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { fieldKey, type, label, placeholder, required } = attributes;
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
	const keyOptions = [
		{ label: __( 'Select a field', 'yaycheckout' ), value: '' },
		...catalog
			.filter( ( field ) => field.key === fieldKey || ! usedKeys.includes( field.key ) )
			.map( ( field ) => ( { label: `${ field.label } (${ field.key })`, value: field.key } ) ),
	];
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
								setAttributes(
									def
										? {
												fieldKey: def.key,
												type: def.type,
												label: def.label,
												required: def.required,
										  }
										: { fieldKey: value }
								);
							} }
						/>
					) }
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'yaycheckout' ) }
						checked={ required }
						onChange={ ( value ) => setAttributes( { required: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<FieldPreview
					label={ canvasLabel }
					type={ type }
					required={ required }
					placeholder={ placeholder }
					labelStyle={ labelStyle }
				/>
			</div>
		</>
	);
}
