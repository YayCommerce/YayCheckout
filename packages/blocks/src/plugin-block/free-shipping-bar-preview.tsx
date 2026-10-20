import { __ } from '@wordpress/i18n';

/**
 * A stand-in for YayBoost's free shipping bar in its spot (state `shortcode`): FreeShippingBarDesigns' minimal-text
 * bar, YayBoost's default, with a sample message. YayBoost's own styles don't load in the canvas, so editor.css
 * draws it, in the layout's Primary. Not part of the block: no pointer events, so clicks select the block.
 */
export function FreeShippingBarPreview() {
	return (
		<div className="yayboost-shipping-bar yayboost-shipping-bar--minimal-text yaycheckout-plugin-block__preview" aria-hidden="true" contentEditable={ false }>
			<div className="yayboost-shipping-bar__icon">🚚</div>
			<div className="yayboost-shipping-bar__message">{ __( 'Add $25.00 more for free shipping!', 'yaycheckout' ) }</div>
		</div>
	);
}
