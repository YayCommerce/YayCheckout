import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { ExternalLink, Notice, PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { loginEditorData } from './editor-data';

const DEFAULT_LINK_TEXT = 'Log in';

interface LoginAttributes {
	text: string;
	linkText: string;
	behavior: string;
}

interface EditProps {
	attributes: LoginAttributes;
	setAttributes: ( attributes: Partial< LoginAttributes > ) => void;
	isSelected: boolean;
}

/**
 * Same split as CheckoutLogin::split_link(): the first occurrence of the link
 * text is the control; a missing phrase is appended.
 */
function splitLink( text: string, linkText: string ): [ string, string, string ] {
	const link = linkText || DEFAULT_LINK_TEXT;
	const at = text.indexOf( link );

	if ( linkText === '' || at === -1 ) {
		return [ `${ text } `, link, '' ];
	}

	return [ text.slice( 0, at ), link, text.slice( at + link.length ) ];
}

export default function Edit( { attributes, setAttributes, isSelected }: EditProps ) {
	const { text = 'Returning customers? Log in', linkText = DEFAULT_LINK_TEXT, behavior = 'modal' } = attributes;
	const blockProps = useBlockProps( {
		className: 'yaycheckout-login',
		style: { fontSize: 'inherit', fontWeight: 'inherit' },
	} );
	const [ before, link, after ] = splitLink( text, linkText );
	const linkMissing = linkText !== '' && ! text.includes( linkText );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Login', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Clicking the link', 'yaycheckout' ) }
						value={ behavior }
						options={ [
							{ label: __( 'Open a login modal', 'yaycheckout' ), value: 'modal' },
							{ label: __( 'Go to the login page', 'yaycheckout' ), value: 'page' },
						] }
						onChange={ ( value ) => setAttributes( { behavior: value } ) }
					/>
					<TextControl
						label={ __( 'Link text', 'yaycheckout' ) }
						value={ linkText }
						help={ __( 'The phrase in the sentence that receives the click.', 'yaycheckout' ) }
						onChange={ ( value ) => setAttributes( { linkText: value } ) }
					/>
					{ linkMissing && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Link text is not in the sentence, so it is appended.', 'yaycheckout' ) }
						</Notice>
					) }
					<p>{ __( 'Logged-out shoppers see this block.', 'yaycheckout' ) }</p>
				</PanelBody>
			</InspectorControls>
			{ ! loginEditorData.reminderEnabled && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Shoppers will not see this block until Enable log-in during checkout is turned on.',
						'yaycheckout'
					) }{ ' ' }
					{ loginEditorData.settingsUrl !== '' && (
						<ExternalLink href={ loginEditorData.settingsUrl }>
							{ __( 'Accounts & Privacy', 'yaycheckout' ) }
						</ExternalLink>
					) }
				</Notice>
			) }
			{ isSelected ? (
				<RichText
					{ ...blockProps }
					tagName="p"
					allowedFormats={ [] }
					value={ text }
					onChange={ ( value ) => setAttributes( { text: value } ) }
				/>
			) : (
				<p { ...blockProps }>
					{ before }
					<span className="yaycheckout-login__trigger">{ link }</span>
					{ after }
				</p>
			) }
		</>
	);
}
