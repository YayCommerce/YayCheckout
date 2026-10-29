import { __ } from '@wordpress/i18n';

/** The template mock's products; the canvas has no cart to pair from. */
const SAMPLES = [
	{ name: __( 'Merino crew socks (2-pack)', 'yaycheckout' ), price: '$18.00', was: '$24.00', swatch: '#4b5563' },
	{ name: __( 'Washed cotton cap', 'yaycheckout' ), price: '$22.00', was: '$28.00', swatch: '#b45309' },
	{ name: __( 'Fabric care wax', 'yaycheckout' ), price: '$12.00', was: '$15.00', swatch: '#d6d3d1' },
];

/**
 * A stand-in for YayBoost's Frequently bought together in its spot (state `shortcode`): templates/yayboost-fbt.php's
 * markup with sample products, so assets/css/yayboost.css draws it as the storefront does. Not part of the block:
 * no pointer events, so clicks select the block.
 */
export function FbtPreview() {
	return (
		<div className="yaycheckout-fbt yaycheckout-plugin-block__preview" aria-hidden="true" contentEditable={ false }>
			<div className="yaycheckout-fbt__header">
				<span className="yaycheckout-fbt__title">{ __( 'Frequently bought together', 'yaycheckout' ) }</span>
				<span className="yaycheckout-fbt__caption">{ __( 'Add in one click', 'yaycheckout' ) }</span>
			</div>
			<div className="yaycheckout-fbt__list">
				{ SAMPLES.map( ( sample ) => (
					<div key={ sample.name } className="yaycheckout-fbt__item">
						<span className="yaycheckout-fbt__image" style={ { background: sample.swatch } } />
						<div className="yaycheckout-fbt__body">
							<div className="yaycheckout-fbt__name">{ sample.name }</div>
							<div className="yaycheckout-fbt__price">
								<del>{ sample.was }</del>
								<ins>{ sample.price }</ins>
							</div>
						</div>
						<span className="yaycheckout-fbt__button">
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" focusable="false">
								<path d="M5 12h14" />
								<path d="M12 5v14" />
							</svg>
							{ __( 'Add', 'yaycheckout' ) }
						</span>
					</div>
				) ) }
			</div>
		</div>
	);
}
