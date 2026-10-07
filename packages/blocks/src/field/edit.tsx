import { decodeEntities } from '@wordpress/html-entities';
import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { billingAddressFields, shippingAddressFields, type AddressFieldDef } from '../shared/address-fields';
import { FieldPreview } from '../shared/field-preview';
import { RequiredIndicatorControls } from '../shared/required-indicator-controls';
import { RevealLinkControls, RevealLinkPreview, defaultRevealText, isCollapsed } from '../shared/reveal-link';

// A checkbox and a hidden input show no placeholder.
const NO_PLACEHOLDER_TYPES = [ 'checkbox', 'hidden' ];

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
	collapseBehindLink?: boolean;
	revealText: string;
}

interface EditProps {
	attributes: FieldAttributes;
	setAttributes: ( attributes: Partial< FieldAttributes > ) => void;
	clientId: string;
}

/**
 * The key comes from the section's catalog, unique across the section at any depth.
 * A keyless field takes the first free key: without one it renders under a made-up
 * name that WooCommerce never validates or saves.
 */
export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const {
		fieldKey,
		type,
		label,
		placeholder,
		required,
		requiredMark = '',
		optionalText = '',
		collapseBehindLink,
		revealText = '',
	} = attributes;
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
	// Email is always required; that rule lives in RequiredFieldOverlay::is_required().
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

	const catalogOptions =
		available.length > 0
			? available.map( ( field ) => ( { label: `${ field.label } (${ field.key })`, value: field.key } ) )
			: [ { label: __( 'Every field in this section is already used', 'yaycheckout' ), value: '' } ];
	// A key saved before the catalog dropped it, such as billing_email, stays selected.
	const keyOptions =
		fieldKey !== '' && ! catalog.some( ( field ) => field.key === fieldKey )
			? [ { label: `${ decodeEntities( label ) || fieldKey } (${ fieldKey })`, value: fieldKey }, ...catalogOptions ]
			: catalogOptions;
	const blockProps = useBlockProps( { className: 'yaycheckout-field' } );
	const labelPlaceholder = fieldKey || __( 'Field', 'yaycheckout' );
	// A hidden input has nothing for the shopper to open.
	const canCollapse = type !== 'hidden';
	const collapsed = canCollapse && isCollapsed( fieldKey, isEmail || required, collapseBehindLink );

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
						value={ decodeEntities( label ) }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					{ ! NO_PLACEHOLDER_TYPES.includes( type ) && (
						<TextControl
							label={ __( 'Placeholder', 'yaycheckout' ) }
							value={ placeholder }
							onChange={ ( value ) => setAttributes( { placeholder: value } ) }
						/>
					) }
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
					{ canCollapse && (
						<RevealLinkControls
							fieldKey={ fieldKey }
							label={ label || labelPlaceholder }
							required={ isEmail || required }
							collapseBehindLink={ collapseBehindLink }
							revealText={ revealText }
							setAttributes={ setAttributes }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<RevealLinkPreview
					collapsed={ collapsed }
					text={ revealText || defaultRevealText( fieldKey, label || labelPlaceholder ) }
				>
					<FieldPreview
						label={ label }
						onLabelChange={ ( value ) => setAttributes( { label: value } ) }
						labelPlaceholder={ labelPlaceholder }
						type={ type }
						required={ isEmail || required }
						requiredMark={ requiredMark }
						optionalText={ optionalText }
						placeholder={ placeholder }
						inheritLabelFont
					/>
				</RevealLinkPreview>
			</div>
		</>
	);
}
