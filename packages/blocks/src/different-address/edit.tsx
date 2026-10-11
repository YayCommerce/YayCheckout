import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
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

/**
 * Previews the checkbox for WooCommerce's Shipping destination: "Ship to a different address?" when
 * billing is the main address, "Use same address for billing" when shipping is. See render.php.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-different-address' } );
	const shippingIsMain = differentAddressEditorData.destination === 'shipping';
	const name = shippingIsMain ? 'sameLabel' : 'shipLabel';

	return (
		<div { ...blockProps }>
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
	);
}
