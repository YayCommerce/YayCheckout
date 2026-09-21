import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

const buttonStyle = {
	display: 'block',
	width: '100%',
	marginBottom: '8px',
	padding: '10px 12px',
	border: '1px solid #1e1e1e',
	borderRadius: '4px',
	background: '#1e1e1e',
	color: '#fff',
	textAlign: 'center' as const,
	fontSize: '13px',
};

export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-express-pay' } );

	return (
		<div { ...blockProps }>
			<div style={ buttonStyle }>{ __( 'Apple Pay', 'yaycheckout' ) }</div>
			<div style={ { ...buttonStyle, background: '#3858e9', borderColor: '#3858e9' } }>
				{ __( 'Link', 'yaycheckout' ) }
			</div>
		</div>
	);
}
