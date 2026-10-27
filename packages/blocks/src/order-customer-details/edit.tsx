import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './editor.css';

interface OrderCustomerDetailsAttributes {
	billingTitle: string;
	shippingTitle: string;
}

interface EditProps {
	attributes: OrderCustomerDetailsAttributes;
	setAttributes: ( attributes: Partial< OrderCustomerDetailsAttributes > ) => void;
}

/** Also the preview's sample order (Repositories\PreviewOrderRepository::create()); change both together. */
const SAMPLE_BILLING = [ 'John Doe', 'YayCommerce', '7400 Edwards Rd', 'Edwards Rd' ];
const SAMPLE_SHIPPING = [ 'John Doe', 'YayCommerce', '755 E North Grove Rd', 'Mayville, Michigan' ];
const SAMPLE_PHONE = '(910) 529-1147';

const SampleAddress = ( { lines }: { lines: string[] } ) => (
	<address>
		{ lines.map( ( line ) => (
			<span key={ line }>
				{ line }
				<br />
			</span>
		) ) }
		<a href="#" onClick={ ( event ) => event.preventDefault() }>
			{ SAMPLE_PHONE }
		</a>
	</address>
);

/**
 * Preview of WooCommerce's order customer details. Address titles are RichText; empty attributes
 * fall back to WooCommerce's strings on the storefront.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { billingTitle, shippingTitle } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-order-customer-details' } );

	return (
		<div { ...blockProps }>
			<section className="yaycheckout-order-customer-details__preview woocommerce-customer-details">
				<div className="yaycheckout-order-customer-details__columns">
					<div>
						<RichText
							tagName="h2"
							className="woocommerce-column__title"
							value={ billingTitle }
							onChange={ ( value: string ) => setAttributes( { billingTitle: value } ) }
							placeholder={ __( 'Billing address', 'yaycheckout' ) }
							allowedFormats={ [] }
							withoutInteractiveFormatting
						/>
						<SampleAddress lines={ SAMPLE_BILLING } />
					</div>
					<div>
						<RichText
							tagName="h2"
							className="woocommerce-column__title"
							value={ shippingTitle }
							onChange={ ( value: string ) => setAttributes( { shippingTitle: value } ) }
							placeholder={ __( 'Shipping address', 'yaycheckout' ) }
							allowedFormats={ [] }
							withoutInteractiveFormatting
						/>
						<SampleAddress lines={ SAMPLE_SHIPPING } />
					</div>
				</div>
			</section>
		</div>
	);
}
