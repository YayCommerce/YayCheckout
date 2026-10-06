import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './editor.css';

/**
 * Static stand-in: WooCommerce draws the real table from the cart, which the
 * editor does not have.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-order-review' } );

	return (
		<div { ...blockProps }>
			<div className="yaycheckout-order-review__placeholder">
				{ __(
					'Order summary, payment methods and Place order render here.',
					'yaycheckout'
				) }
			</div>
		</div>
	);
}
