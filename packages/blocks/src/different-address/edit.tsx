import { InspectorControls, RichText, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { ExternalLink, Notice, PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { plainText } from '../shared/plain-text';
import { ADDRESS_SECTIONS, type BlockEditorSelectors, sectionFields } from '../shared/section-fields';
import { TEXT_FORMATS_WITH_LIVE_VALUE } from '../shared/text-formats';
import { differentAddressEditorData, type ShipToDestination } from './editor-data';
import './editor.css';

interface DifferentAddressAttributes {
	shipLabel: string;
	sameLabel: string;
}

interface EditProps {
	attributes: DifferentAddressAttributes;
	isSelected: boolean;
	setAttributes: ( attributes: Partial< DifferentAddressAttributes > ) => void;
}

/**
 * When billing is the same as shipping, each Billing field either takes its shipping twin's value or,
 * when it is no address field, hides with Billing. Says which fields lose out.
 */
function billingWarnings( editor: BlockEditorSelectors ): string[] {
	const shippingKeys = new Set( sectionFields( editor, ADDRESS_SECTIONS.shipping ).map( ( field ) => field.key ) );

	return sectionFields( editor, ADDRESS_SECTIONS.billing ).flatMap( ( { key, attributes } ) => {
		const label = plainText( attributes.label ) || key;
		const suffix = key.replace( /^billing_/, '' );

		if ( ! key.startsWith( 'billing_' ) || ! differentAddressEditorData.copiedKeys.includes( suffix ) ) {
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

/** What the block shows for each WooCommerce Shipping destination; render.php prints the same. */
const MODES: Record<
	ShipToDestination,
	{ mainAddress: string; unprinted: string; label: keyof DifferentAddressAttributes; placeholder: string }
> = {
	billing: {
		mainAddress: __( 'Billing is the main address.', 'yaycheckout' ),
		unprinted: '',
		label: 'shipLabel',
		placeholder: __( 'Ship to a different address?', 'yaycheckout' ),
	},
	shipping: {
		mainAddress: __( 'Shipping is the main address.', 'yaycheckout' ),
		unprinted: '',
		label: 'sameLabel',
		placeholder: __( 'Use same address for billing', 'yaycheckout' ),
	},
	billing_only: {
		mainAddress: __( 'Shipping is forced to the billing address.', 'yaycheckout' ),
		unprinted: __( 'WooCommerce ships to the billing address only, so this block prints nothing.', 'yaycheckout' ),
		label: 'shipLabel',
		placeholder: __( 'Ship to a different address?', 'yaycheckout' ),
	},
};

/**
 * Previews the checkbox for WooCommerce's Shipping destination: "Ship to a different address?" when
 * billing is the main address, "Use same address for billing" when shipping is. See render.php.
 * The editor never hides Billing or Shipping, so both stay editable.
 */
export default function Edit( { attributes, isSelected, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-different-address' } );
	const { destination, settingsUrl } = differentAddressEditorData;
	const shippingIsMain = destination === 'shipping';
	const mode = MODES[ destination ];

	const hasShipping = useSelect(
		( select ) =>
			( select( blockEditorStore ) as unknown as BlockEditorSelectors ).getBlocksByName( ADDRESS_SECTIONS.shipping )
				.length > 0,
		[]
	);
	// The warnings show only in the sidebar, so the fields are walked only while the block is selected.
	const warnings = useSelect(
		( select ) =>
			shippingIsMain && isSelected
				? billingWarnings( select( blockEditorStore ) as unknown as BlockEditorSelectors )
				: [],
		[ shippingIsMain, isSelected ]
	);
	const unprinted =
		mode.unprinted ||
		( hasShipping ? '' : __( 'This layout has no Shipping section, so this block prints nothing.', 'yaycheckout' ) );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Main address', 'yaycheckout' ) }>
					<p>
						{ mode.mainAddress }{ ' ' }
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
						value={ attributes[ mode.label ] }
						onChange={ ( value: string ) => setAttributes( { [ mode.label ]: value } ) }
						placeholder={ mode.placeholder }
						allowedFormats={ TEXT_FORMATS_WITH_LIVE_VALUE }
					/>
				</div>
			</div>
		</>
	);
}
