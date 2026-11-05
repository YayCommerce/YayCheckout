import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.css';

type LabelKey = 'bankLabel' | 'accountNumberLabel' | 'sortCodeLabel' | 'ibanLabel' | 'bicLabel';

interface PaymentInstructionsAttributes {
	showTitle: boolean;
	title: string;
	bankLabel: string;
	accountNumberLabel: string;
	sortCodeLabel: string;
	ibanLabel: string;
	bicLabel: string;
}

interface EditProps {
	attributes: PaymentInstructionsAttributes;
	setAttributes: ( attributes: Partial< PaymentInstructionsAttributes > ) => void;
}

/**
 * Direct bank transfer's account fields, in the order WC_Gateway_BACS::bank_details() prints them: the class
 * WooCommerce gives the item, the attribute holding its label, WooCommerce's label and a sample value. Mirrors
 * PaymentInstructions::FIELD_LABELS.
 */
const SAMPLE_ACCOUNT: Array< [ string, LabelKey, string, string ] > = [
	[ 'bank_name', 'bankLabel', __( 'Bank', 'yaycheckout' ), 'Example Bank' ],
	[ 'account_number', 'accountNumberLabel', __( 'Account number', 'yaycheckout' ), '12345678' ],
	[ 'sort_code', 'sortCodeLabel', __( 'Sort code', 'yaycheckout' ), '12-34-56' ],
	[ 'iban', 'ibanLabel', __( 'IBAN', 'yaycheckout' ), 'GB33BUKB20201555555555' ],
	[ 'bic', 'bicLabel', __( 'BIC', 'yaycheckout' ), 'BUKBGB22' ],
];

/**
 * A sample of what Direct bank transfer prints, in WooCommerce's own markup so the storefront's styles read
 * it alike. The title and labels are RichText; empty ones keep WooCommerce's text on the storefront. The
 * storefront prints whatever the order's payment method adds, or nothing.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { title } = attributes;
	const showTitle = attributes.showTitle !== false;
	const blockProps = useBlockProps( { className: 'yaycheckout-payment-instructions' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show title', 'yaycheckout' ) }
						help={ __( 'The heading above the bank details of Direct bank transfer.', 'yaycheckout' ) }
						checked={ showTitle }
						onChange={ ( value ) => setAttributes( { showTitle: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<section className="woocommerce-bacs-bank-details">
					{ showTitle && (
						<RichText
							tagName="h2"
							className="wc-bacs-bank-details-heading"
							value={ title }
							onChange={ ( value: string ) => setAttributes( { title: value } ) }
							placeholder={ __( 'Our bank details', 'yaycheckout' ) }
							allowedFormats={ [] }
							withoutInteractiveFormatting
						/>
					) }
					<h3 className="wc-bacs-bank-details-account-name">{ __( 'Store name:', 'yaycheckout' ) }</h3>
					<ul className="wc-bacs-bank-details order_details bacs_details">
						{ SAMPLE_ACCOUNT.map( ( [ field, key, placeholder, value ] ) => (
							<li key={ field } className={ field }>
								<RichText
									tagName="span"
									value={ attributes[ key ] }
									onChange={ ( text: string ) => setAttributes( { [ key ]: text } ) }
									placeholder={ placeholder }
									allowedFormats={ [] }
									withoutInteractiveFormatting
								/>
								: <strong>{ value }</strong>
							</li>
						) ) }
					</ul>
				</section>
				<p className="yaycheckout-payment-instructions__note">
					{ __(
						"Sample. Shoppers see the instructions of their order's payment method, such as your bank details for Direct bank transfer, or nothing when it has none.",
						'yaycheckout'
					) }
				</p>
			</div>
		</>
	);
}
