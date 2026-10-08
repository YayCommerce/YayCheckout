import { TextControl, ToggleControl } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';
import './reveal-link.css';

/** Mirrors RevealLink::APARTMENT_KEYS in PHP: collapsed unless the merchant turns it off. */
const APARTMENT_KEYS = [ 'billing_address_2', 'shipping_address_2' ];

export interface RevealLinkAttributes {
	collapseBehindLink?: boolean;
	revealText?: string;
}

/** Mirrors RevealLink::collapsed(): a required field is never collapsed. */
export function isCollapsed( fieldKey: string, required: boolean, collapseBehindLink?: boolean ): boolean {
	if ( required ) {
		return false;
	}

	return typeof collapseBehindLink === 'boolean' ? collapseBehindLink : APARTMENT_KEYS.includes( fieldKey );
}

/** Mirrors RevealLink::text()'s fallback, including keeping a leading acronym's capitals. */
export function defaultRevealText( fieldKey: string, label: string ): string {
	if ( APARTMENT_KEYS.includes( fieldKey ) ) {
		return __( '+ Add apartment, suite, etc.', 'yaycheckout' );
	}

	const plain = decodeEntities( label );
	const lowered = /^[A-Z](?![A-Z])/.test( plain ) ? plain.charAt( 0 ).toLowerCase() + plain.slice( 1 ) : plain;

	/* translators: %s: field label, e.g. "company name". */
	return sprintf( __( '+ Add %s', 'yaycheckout' ), lowered );
}

interface RevealLinkControlsProps extends RevealLinkAttributes {
	fieldKey: string;
	label: string;
	required: boolean;
	setAttributes: ( attributes: RevealLinkAttributes ) => void;
}

/** The sidebar's "Hide behind a link" toggle and, while it is on, the link text. */
export function RevealLinkControls( {
	fieldKey,
	label,
	required,
	collapseBehindLink,
	revealText = '',
	setAttributes,
}: RevealLinkControlsProps ) {
	const collapsed = isCollapsed( fieldKey, required, collapseBehindLink );

	return (
		<>
			<ToggleControl
				label={ __( 'Hide behind a link', 'yaycheckout' ) }
				checked={ collapsed }
				disabled={ required }
				help={
					required
						? __( 'A required field is always shown.', 'yaycheckout' )
						: __( 'Shoppers see a link and open the field when they need it.', 'yaycheckout' )
				}
				onChange={ ( value ) => setAttributes( { collapseBehindLink: value } ) }
			/>
			{ collapsed && (
				<TextControl
					label={ __( 'Link text', 'yaycheckout' ) }
					value={ revealText }
					placeholder={ defaultRevealText( fieldKey, label ) }
					onChange={ ( value ) => setAttributes( { revealText: value } ) }
				/>
			) }
		</>
	);
}

interface RevealLinkPreviewProps {
	collapsed: boolean;
	text: string;
	children: JSX.Element;
}

/** On the canvas a collapsed field shows the shopper's link, then the field faded but still editable. */
export function RevealLinkPreview( { collapsed, text, children }: RevealLinkPreviewProps ) {
	if ( ! collapsed ) {
		return <>{ children }</>;
	}

	return (
		<>
			<span className="yaycheckout-reveal-link">{ text }</span>
			<div className="yaycheckout-reveal-preview">{ children }</div>
		</>
	);
}
