import { SelectControl, TextControl } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';
import './disclosure.css';

export type DisclosureMode = 'none' | 'link' | 'collapsible' | 'checkbox';

/** Mirrors Disclosure::APARTMENT_KEYS in PHP: behind a link unless the merchant says otherwise. */
const APARTMENT_KEYS = [ 'billing_address_2', 'shipping_address_2' ];

const MODES: DisclosureMode[] = [ 'none', 'link', 'collapsible', 'checkbox' ];

export interface DisclosureAttributes {
	disclosure?: DisclosureMode;
	disclosureText?: string;
}

/** Mirrors Disclosure::mode(): a required field is always shown. */
export function disclosureMode( fieldKey: string, required: boolean, disclosure?: DisclosureMode ): DisclosureMode {
	if ( required ) {
		return 'none';
	}

	if ( disclosure && MODES.includes( disclosure ) ) {
		return disclosure;
	}

	return APARTMENT_KEYS.includes( fieldKey ) ? 'link' : 'none';
}

/** Mirrors Disclosure::text()'s fallback, including keeping a leading acronym's capitals. */
export function defaultDisclosureText( fieldKey: string, label: string, mode: DisclosureMode ): string {
	const plain = decodeEntities( label );

	if ( mode === 'collapsible' ) {
		return plain;
	}

	if ( APARTMENT_KEYS.includes( fieldKey ) ) {
		return mode === 'checkbox'
			? __( 'Add apartment, suite, etc.', 'yaycheckout' )
			: __( '+ Add apartment, suite, etc.', 'yaycheckout' );
	}

	const lowered = /^[A-Z](?![A-Z])/.test( plain ) ? plain.charAt( 0 ).toLowerCase() + plain.slice( 1 ) : plain;

	if ( mode === 'checkbox' ) {
		/* translators: %s: field label, e.g. "company name". */
		return sprintf( __( 'Add %s', 'yaycheckout' ), lowered );
	}

	/* translators: %s: field label, e.g. "company name". */
	return sprintf( __( '+ Add %s', 'yaycheckout' ), lowered );
}

const TEXT_LABELS: Record< Exclude< DisclosureMode, 'none' >, string > = {
	link: __( 'Link text', 'yaycheckout' ),
	collapsible: __( 'Header text', 'yaycheckout' ),
	checkbox: __( 'Checkbox label', 'yaycheckout' ),
};

interface DisclosureControlsProps extends DisclosureAttributes {
	fieldKey: string;
	label: string;
	required: boolean;
	setAttributes: ( attributes: DisclosureAttributes ) => void;
}

/** The sidebar's "Show field" choice and, while the field hides, the link or header text. */
export function DisclosureControls( {
	fieldKey,
	label,
	required,
	disclosure,
	disclosureText = '',
	setAttributes,
}: DisclosureControlsProps ) {
	const mode = disclosureMode( fieldKey, required, disclosure );

	return (
		<>
			<SelectControl
				label={ __( 'Show field', 'yaycheckout' ) }
				value={ mode }
				disabled={ required }
				options={ [
					{ label: __( 'Always', 'yaycheckout' ), value: 'none' },
					{ label: __( 'Behind a link', 'yaycheckout' ), value: 'link' },
					{ label: __( 'In a collapsible section', 'yaycheckout' ), value: 'collapsible' },
					{ label: __( 'Behind a checkbox', 'yaycheckout' ), value: 'checkbox' },
				] }
				help={
					required
						? __( 'A required field is always shown.', 'yaycheckout' )
						: __( 'Shoppers open the field when they need it.', 'yaycheckout' )
				}
				onChange={ ( value ) => setAttributes( { disclosure: value as DisclosureMode } ) }
			/>
			{ mode !== 'none' && (
				<TextControl
					label={ TEXT_LABELS[ mode ] }
					value={ disclosureText }
					placeholder={ defaultDisclosureText( fieldKey, label, mode ) }
					onChange={ ( value ) => setAttributes( { disclosureText: value } ) }
				/>
			) }
		</>
	);
}

interface DisclosurePreviewProps {
	mode: DisclosureMode;
	fieldKey: string;
	label: string;
	disclosureText: string;
	children: JSX.Element;
}

/**
 * On the canvas a hidden field shows the shopper's link or header, then the field faded but still editable.
 * The text is the merchant's, else defaultDisclosureText().
 */
export function DisclosurePreview( { mode, fieldKey, label, disclosureText, children }: DisclosurePreviewProps ) {
	if ( mode === 'none' ) {
		return <>{ children }</>;
	}

	const text = disclosureText || defaultDisclosureText( fieldKey, label, mode );

	return (
		<>
			<span className="yaycheckout-disclosure__toggle" data-mode={ mode }>
				{ mode === 'checkbox' && <input type="checkbox" className="yaycheckout-disclosure__checkbox" disabled /> }
				<span>{ text }</span>
				{ mode === 'collapsible' && <Chevron className="yaycheckout-disclosure__chevron" /> }
			</span>
			<div className="yaycheckout-disclosure__preview">{ children }</div>
		</>
	);
}

/** The down chevron a collapsible toggle shows. A copy of Disclosure::chevron(). */
export function Chevron( { className }: { className: string } ) {
	return (
		<svg className={ className } viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<path d="M17.5 11.6 12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2Z" />
		</svg>
	);
}
