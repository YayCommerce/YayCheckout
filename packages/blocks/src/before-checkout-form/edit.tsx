import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

const frame = {
	minHeight: '48px',
	padding: '12px',
	border: '1px dashed #c3c4c7',
	color: '#646970',
	fontSize: '13px',
	textAlign: 'center' as const,
};

export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-before-checkout-form' } );

	return (
		<div { ...blockProps } style={ frame }>
			{ __( 'Woo before checkout form', 'yaycheckout' ) }
		</div>
	);
}
