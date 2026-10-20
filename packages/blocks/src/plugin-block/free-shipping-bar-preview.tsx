import { __ } from '@wordpress/i18n';

/**
 * A stand-in for the free shipping bar in its spot (state `shortcode`): the markup the spot prints
 * (templates/yayboost-free-shipping-bar.php), with the template mock's sample cart. yayboost.css draws it, as on the
 * storefront. Not part of the block: no pointer events, so clicks select the block.
 */
export function FreeShippingBarPreview() {
	return (
		<div className="yaycheckout-shipping-bar yaycheckout-plugin-block__preview" aria-hidden="true" contentEditable={ false }>
			<div className="yaycheckout-shipping-bar__head">
				<span className="yaycheckout-shipping-bar__message">{ __( 'Add $52.00 more for free shipping', 'yaycheckout' ) }</span>
				<span className="yaycheckout-shipping-bar__amount">$98.00 / $150.00</span>
			</div>
			<div className="yaycheckout-shipping-bar__track">
				<div className="yaycheckout-shipping-bar__fill" style={ { width: '65.33%' } } />
			</div>
		</div>
	);
}
