import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

/**
 * Static stand-in: WooCommerce draws the real table from the cart, which the
 * editor does not have.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-order-review' } );

	return (
		<div { ...blockProps }>
			<div
				style={ {
					border: '1px dashed #949494',
					borderRadius: 4,
					padding: '24px 16px',
					textAlign: 'center',
					color: '#757575',
				} }
			>
				{ __(
					'Order summary, payment methods and Place order render here.',
					'yaycheckout'
				) }
			</div>
		</div>
	);
}
