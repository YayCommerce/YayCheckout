import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './editor.css';

export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-express-pay' } );

	return (
		<div { ...blockProps }>
			<div className="yaycheckout-express-pay__button">{ __( 'Apple Pay', 'yaycheckout' ) }</div>
			<div className="yaycheckout-express-pay__button yaycheckout-express-pay__button--link">
				{ __( 'Link', 'yaycheckout' ) }
			</div>
		</div>
	);
}
