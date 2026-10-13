import { RichText, useBlockProps } from '@wordpress/block-editor';
import { ExternalLink, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { FieldPreview } from '../shared/field-preview';
import { TEXT_FORMATS_WITH_LIVE_VALUE } from '../shared/text-formats';
import { createAccountEditorData } from './editor-data';
import './editor.css';

interface CreateAccountAttributes {
	checkboxLabel: string;
}

interface EditProps {
	attributes: CreateAccountAttributes;
	setAttributes: ( attributes: Partial< CreateAccountAttributes > ) => void;
}

/**
 * Static editor preview of the account fields. The storefront follows
 * WooCommerce's Accounts & Privacy settings; see render.php.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { checkboxLabel } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-create-account' } );

	return (
		<>
			<div { ...blockProps }>
				{ ! createAccountEditorData.signupEnabled && (
					<Notice
						className="yaycheckout-create-account__notice"
						status="warning"
						isDismissible={ false }
					>
						{ __(
							'Shoppers will not see this block until account creation during checkout is turned on.',
							'yaycheckout'
						) }{ ' ' }
						{ createAccountEditorData.settingsUrl !== '' && (
							<ExternalLink href={ createAccountEditorData.settingsUrl }>
								{ __( 'Accounts & Privacy', 'yaycheckout' ) }
							</ExternalLink>
						) }
					</Notice>
				) }
				<div className="yaycheckout-create-account__checkbox">
					<input type="checkbox" disabled />
					{ /* A span, not a label: typing Space would toggle a label's checkbox. */ }
					<RichText
						tagName="span"
						value={ checkboxLabel }
						onChange={ ( value: string ) => setAttributes( { checkboxLabel: value } ) }
						placeholder={ __( 'Create an account?', 'yaycheckout' ) }
						allowedFormats={ TEXT_FORMATS_WITH_LIVE_VALUE }
					/>
				</div>
				<FieldPreview
					label={ __( 'Create account password', 'yaycheckout' ) }
					type="password"
					required
				/>
			</div>
		</>
	);
}
