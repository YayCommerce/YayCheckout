import { InspectorControls, RichText, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { ExternalLink, Notice, PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { plainText } from '../shared/plain-text';
import { differentAddressEditorData } from './editor-data';
import './editor.css';

interface DifferentAddressAttributes {
	shipLabel: string;
	sameLabel: string;
}

interface EditProps {
	attributes: DifferentAddressAttributes;
	setAttributes: ( attributes: Partial< DifferentAddressAttributes > ) => void;
}

interface BlockEditorSelectors {
	getBlocksByName: ( name: string ) => string[];
	getClientIdsOfDescendants: ( clientIds: string[] ) => string[];
	getBlockName: ( clientId: string ) => string | null;
	getBlockAttributes: ( clientId: string ) => Record< string, unknown > | null;
}

interface SectionField {
	key: string;
	label: string;
}

/** Mirrors CheckoutDifferentAddress::COPIED_KEYS in PHP: the billing keys copied from their shipping twins. */
const COPIED_KEYS = [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ];

/** The Field blocks inside a section, with the label each prints. */
function sectionFields( editor: BlockEditorSelectors, blockName: string ): SectionField[] {
	return editor.getBlocksByName( blockName ).flatMap( ( sectionId ) =>
		editor
			.getClientIdsOfDescendants( [ sectionId ] )
			.filter( ( clientId ) => editor.getBlockName( clientId ) === 'yaycheckout/field' )
			.map( ( clientId ) => {
				const attributes = editor.getBlockAttributes( clientId ) ?? {};
				const key = String( attributes.fieldKey ?? '' ).trim();

				return { key, label: plainText( attributes.label ) || key };
			} )
			.filter( ( field ) => field.key !== '' )
	);
}

/**
 * When billing is the same as shipping, each Billing field either takes its shipping twin's value or,
 * when it is no address field, hides with Billing. Says which fields lose out.
 */
function billingWarnings( editor: BlockEditorSelectors ): string[] {
	const shippingKeys = new Set( sectionFields( editor, 'yaycheckout/shipping' ).map( ( field ) => field.key ) );

	return sectionFields( editor, 'yaycheckout/billing' ).flatMap( ( { key, label } ) => {
		const suffix = key.replace( /^billing_/, '' );

		if ( ! key.startsWith( 'billing_' ) || ! COPIED_KEYS.includes( suffix ) ) {
			/* translators: %s: field label. */
			return [ sprintf( __( '%s is hidden when billing is the same as shipping; move it out of Billing.', 'yaycheckout' ), label ) ];
		}

		if ( ! shippingKeys.has( `shipping_${ suffix }` ) ) {
			/* translators: %s: field label. */
			return [ sprintf( __( '%s will be saved empty when billing is the same as shipping.', 'yaycheckout' ), label ) ];
		}

		return [];
	} );
}

/**
 * Previews the checkbox for WooCommerce's Shipping destination: "Ship to a different address?" when
 * billing is the main address, "Use same address for billing" when shipping is. See render.php.
 * The editor never hides Billing or Shipping, so both stay editable.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-different-address' } );
	const { destination, settingsUrl } = differentAddressEditorData;
	const shippingIsMain = destination === 'shipping';
	const name = shippingIsMain ? 'sameLabel' : 'shipLabel';

	const hasShipping = useSelect(
		( select ) =>
			( select( blockEditorStore ) as unknown as BlockEditorSelectors ).getBlocksByName( 'yaycheckout/shipping' )
				.length > 0,
		[]
	);
	// Joined, so useSelect sees an equal value while nothing changed.
	const warningText = useSelect(
		( select ) =>
			shippingIsMain
				? billingWarnings( select( blockEditorStore ) as unknown as BlockEditorSelectors ).join( '\n' )
				: '',
		[ shippingIsMain ]
	);
	const warnings = warningText === '' ? [] : warningText.split( '\n' );

	let unprinted = '';

	if ( destination === 'billing_only' ) {
		unprinted = __( 'WooCommerce ships to the billing address only, so this block prints nothing.', 'yaycheckout' );
	} else if ( ! hasShipping ) {
		unprinted = __( 'This layout has no Shipping section, so this block prints nothing.', 'yaycheckout' );
	}

	let mainAddress = __( 'Billing is the main address.', 'yaycheckout' );

	if ( shippingIsMain ) {
		mainAddress = __( 'Shipping is the main address.', 'yaycheckout' );
	} else if ( destination === 'billing_only' ) {
		mainAddress = __( 'Shipping is forced to the billing address.', 'yaycheckout' );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Main address', 'yaycheckout' ) }>
					<p>
						{ mainAddress }{ ' ' }
						{ settingsUrl !== '' && (
							<ExternalLink href={ settingsUrl }>
								{ __( 'Change in WooCommerce settings', 'yaycheckout' ) }
							</ExternalLink>
						) }
					</p>
					{ warnings.length > 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ warnings.map( ( warning ) => (
								<p key={ warning }>{ warning }</p>
							) ) }
						</Notice>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ unprinted !== '' && (
					<Notice className="yaycheckout-different-address__notice" status="warning" isDismissible={ false }>
						{ unprinted }
					</Notice>
				) }
				<div className="yaycheckout-different-address__checkbox">
					<input type="checkbox" checked={ shippingIsMain } disabled readOnly />
					{ /* A span, not a label: typing Space would toggle a label's checkbox. */ }
					<RichText
						tagName="span"
						value={ attributes[ name ] }
						onChange={ ( value: string ) => setAttributes( { [ name ]: value } ) }
						placeholder={
							shippingIsMain
								? __( 'Use same address for billing', 'yaycheckout' )
								: __( 'Ship to a different address?', 'yaycheckout' )
						}
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
				</div>
			</div>
		</>
	);
}
