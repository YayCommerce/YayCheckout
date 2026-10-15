import { useBlockProps } from '@wordpress/block-editor';
import { ExternalLink, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { expressPayEditorData } from './editor-data';
import './editor.css';

/**
 * Static preview of wallet buttons. The storefront shows the buttons the store's gateways print, which
 * depend on the gateway's settings and the shopper's browser; see render.php.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-express-pay' } );

	return (
		<div { ...blockProps }>
			{ ! expressPayEditorData.hasButtons && (
				<Notice className="yaycheckout-express-pay__notice" status="warning" isDismissible={ false }>
					{ __(
						'Shoppers will not see this block until a payment gateway with express checkout, such as Stripe, is turned on.',
						'yaycheckout'
					) }{ ' ' }
					{ expressPayEditorData.settingsUrl !== '' && (
						<ExternalLink href={ expressPayEditorData.settingsUrl }>
							{ __( 'Payments', 'yaycheckout' ) }
						</ExternalLink>
					) }
				</Notice>
			) }
			<div className="yaycheckout-express-pay__buttons">
				<div className="yaycheckout-express-pay__button yaycheckout-express-pay__button--apple">
					{ __( 'Apple Pay', 'yaycheckout' ) }
				</div>
				<div className="yaycheckout-express-pay__button yaycheckout-express-pay__button--google">
					{ __( 'Google Pay', 'yaycheckout' ) }
				</div>
				<div className="yaycheckout-express-pay__button yaycheckout-express-pay__button--paypal">
					{ __( 'PayPal', 'yaycheckout' ) }
				</div>
			</div>
		</div>
	);
}
