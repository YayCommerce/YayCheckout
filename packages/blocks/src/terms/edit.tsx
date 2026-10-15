import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

/**
 * A sample of what render.php prints through WooCommerce's checkout/terms.php: the privacy text and the terms
 * checkbox. The storefront shows WooCommerce's own wording, and only the parts its settings turn on.
 */
/** A preview link takes the link colour but goes nowhere. */
const preventDefault = ( event: { preventDefault: () => void } ) => event.preventDefault();

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div className="woocommerce-terms-and-conditions-wrapper">
				<div className="woocommerce-privacy-policy-text">
					<p>
						{ __(
							'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our',
							'yaycheckout'
						) }{ ' ' }
						<a href="#" onClick={ preventDefault }>
							{ __( 'privacy policy', 'yaycheckout' ) }
						</a>
						.
					</p>
				</div>
				<p className="form-row validate-required">
					<label className="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
						<input type="checkbox" className="woocommerce-form__input woocommerce-form__input-checkbox" disabled />{ ' ' }
						<span className="woocommerce-terms-and-conditions-checkbox-text">
							{ __( 'I have read and agree to the website', 'yaycheckout' ) }{ ' ' }
								<a href="#" onClick={ preventDefault }>
								{ __( 'terms and conditions', 'yaycheckout' ) }
							</a>
						</span>
						&nbsp;<abbr className="required">*</abbr>
					</label>
				</p>
			</div>
		</div>
	);
}
