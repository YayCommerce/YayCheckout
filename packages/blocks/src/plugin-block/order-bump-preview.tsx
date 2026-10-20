import { __ } from '@wordpress/i18n';

/**
 * A stand-in for YayBoost's order bump in its spot (state `shortcode`): OrderBumpRenderer's single-bump markup with
 * the template mock's offer, so assets/css/yayboost.css draws it as the storefront does. Not part of the block:
 * no pointer events, so clicks select the block.
 */
export function OrderBumpPreview() {
	return (
		<div className="yayboost-order-bumps yaycheckout-plugin-block__preview" aria-hidden="true" contentEditable={ false }>
			<div className="yayboost-order-bump">
				<div className="yayboost-order-bump__inner">
					<div className="yayboost-order-bump__body">
						<h3 className="yayboost-order-bump__headline">{ __( 'One-time offer', 'yaycheckout' ) }</h3>
						<p className="yayboost-order-bump__description">{ __( 'Save 53% at checkout only', 'yaycheckout' ) }</p>
						<p className="yayboost-order-bump__price">
							<span className="yayboost-order-bump__price-bump">$9.00</span>
							<span className="yayboost-order-bump__price-regular">$19.00</span>
						</p>
						<div className="yayboost-order-bump__actions">
							<label className="yayboost-order-bump__checkbox-wrap">
								<input type="checkbox" className="yayboost-order-bump__checkbox" tabIndex={ -1 } readOnly />
								<span className="yayboost-order-bump__checkbox-label">{ __( 'Yes! Add 2-year protection plan', 'yaycheckout' ) }</span>
							</label>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}
