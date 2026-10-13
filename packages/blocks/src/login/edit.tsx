import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { ExternalLink, Notice, PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { loginEditorData } from './editor-data';
import { TRIGGER_CLASS, TRIGGER_FORMAT } from './trigger-format';

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

const TRIGGER_SPAN = new RegExp(
	`<span[^>]*\\bclass=["'][^"']*\\b${ TRIGGER_CLASS }\\b[^"']*["'][^>]*>([\\s\\S]*?)<\\/span>`,
	'i'
);

function hasTriggerSpan( html: string ): boolean {
	return TRIGGER_SPAN.test( html );
}

/**
 * RichText value for the canvas. A saved trigger span is used as-is. A legacy
 * block with linkText still in the sentence gets that phrase wrapped for
 * display only, without writing attributes until the merchant edits.
 */
function editorValue( text: string, linkText: string ): string {
	if ( hasTriggerSpan( text ) ) {
		return text;
	}

	if ( linkText !== '' ) {
		const at = text.indexOf( linkText );

		if ( at !== -1 ) {
			return (
				text.slice( 0, at ) +
				`<span class="${ TRIGGER_CLASS }">${ linkText }</span>` +
				text.slice( at + linkText.length )
			);
		}
	}

	return text;
}

function stripTags( html: string ): string {
	return html.replace( /<[^>]*>/g, '' );
}

/**
 * Preview parts for the unselected canvas. Prefers a trigger span, then a
 * legacy linkText match; otherwise the whole sentence is plain.
 */
function previewParts(
	text: string,
	linkText: string
): { before: string; link: string; after: string } | null {
	const match = text.match( TRIGGER_SPAN );

	if ( match && match[ 1 ] !== '' ) {
		const at = match.index ?? 0;

		return {
			before: stripTags( text.slice( 0, at ) ),
			link: stripTags( match[ 1 ] ),
			after: stripTags( text.slice( at + match[ 0 ].length ) ),
		};
	}

	if ( linkText !== '' ) {
		const at = text.indexOf( linkText );

		if ( at !== -1 ) {
			return {
				before: text.slice( 0, at ),
				link: linkText,
				after: text.slice( at + linkText.length ),
			};
		}
	}

	return null;
}

export default function Edit( { attributes, setAttributes, isSelected }: EditProps ) {
	const { text = 'Returning customers? Log in', linkText = 'Log in', behavior = 'modal' } = attributes;
	const blockProps = useBlockProps( {
		className: 'yaycheckout-login',
		style: { fontSize: 'inherit', fontWeight: 'inherit' },
	} );
	const value = editorValue( text, linkText );
	const parts = previewParts( text, linkText );
	const missingTrigger = ! hasTriggerSpan( value );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Clicking the link', 'yaycheckout' ) }
						value={ behavior }
						options={ [
							{ label: __( 'Open a login modal', 'yaycheckout' ), value: 'modal' },
							{ label: __( 'Go to the login page', 'yaycheckout' ), value: 'page' },
						] }
						onChange={ ( next ) => setAttributes( { behavior: next } ) }
					/>
					{ missingTrigger && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Select the words shoppers should click, then choose Login link in the toolbar.',
								'yaycheckout'
							) }
						</Notice>
					) }
					<p>{ __( 'Logged-out shoppers see this block.', 'yaycheckout' ) }</p>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ ! loginEditorData.reminderEnabled && (
					<Notice className="yaycheckout-login__notice" status="warning" isDismissible={ false }>
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
						tagName="p"
						allowedFormats={ [ TRIGGER_FORMAT ] }
						value={ value }
						onChange={ ( next ) => setAttributes( { text: next, linkText: '' } ) }
					/>
				) : parts ? (
					<p>
						{ parts.before }
						<span className={ TRIGGER_CLASS }>{ parts.link }</span>
						{ parts.after }
					</p>
				) : (
					<p>{ stripTags( text ) }</p>
				) }
			</div>
		</>
	);
}
