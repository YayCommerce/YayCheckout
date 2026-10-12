/**
 * The layout fields whose value can be shown back to the shopper, as the editor finds them: Field Value's
 * picker and the live value format's picker list the same fields under the same names.
 */

import { __ } from '@wordpress/i18n';
import emailMeta from '../email/block.json';
import { EMAIL_LABEL } from '../email/defaults';
import { EXTRA_FIELD_LABEL } from '../extra-field/defaults';
import { EXTRA_FIELD_BLOCK, EXTRA_FIELD_KEY_PATTERN } from './extra-field-keys';
import { plainText } from './plain-text';
import { ADDRESS_SECTIONS, type AddressSection, type BlockEditorSelectors, sectionFields } from './section-fields';

export interface ShowableField {
	key: string;
	label: string;
	section: 'billing' | 'shipping' | 'extra';
}

/** Mirrors ShowableFields::UNSHOWABLE_TYPES in PHP: a checkbox's value is always 1, a password must never be echoed, a hidden field holds nothing typed. */
const UNSHOWABLE_TYPES = [ 'checkbox', 'password', 'hidden' ];

/**
 * The layout's fields with the label each prints: Email address (billing_email), the Field blocks
 * inside Billing and inside Shipping, then the Extra fields with a valid Key. A later block with the
 * same Key wins, as LayoutMarkupReader::fields() and ::extra_fields() read them. An empty label falls
 * back as email/render.php, field/render.php and extra-field/render.php print it. Fields with nothing
 * to show are left out, as ShowableFields::labels() does.
 */
export function showableFields( editor: BlockEditorSelectors ): ShowableField[] {
	const fields = new Map< string, ShowableField >();
	const attributesOf = ( clientId: string ) => editor.getBlockAttributes( clientId ) ?? {};

	editor.getBlocksByName( emailMeta.name ).forEach( ( clientId ) => {
		const label = plainText( attributesOf( clientId ).label ) || EMAIL_LABEL;

		fields.set( 'billing_email', { key: 'billing_email', label, section: 'billing' } );
	} );

	( Object.entries( ADDRESS_SECTIONS ) as [ AddressSection, string ][] ).forEach( ( [ section, blockName ] ) => {
		sectionFields( editor, blockName ).forEach( ( { key, attributes } ) => {
			if ( ! UNSHOWABLE_TYPES.includes( String( attributes.type ?? 'text' ) ) ) {
				fields.set( key, { key, label: plainText( attributes.label ) || key, section } );
			}
		} );
	} );

	editor.getBlocksByName( EXTRA_FIELD_BLOCK ).forEach( ( clientId ) => {
		const attributes = attributesOf( clientId );
		const key = String( attributes.fieldKey ?? '' ).trim();
		// A single checkbox's value is always 1.
		const singleCheckbox = attributes.type === 'checkbox' && ! ( Array.isArray( attributes.options ) && attributes.options.length > 0 );

		// An address field with the same Key keeps it, as ShowableFields::labels() does.
		if ( EXTRA_FIELD_KEY_PATTERN.test( key ) && ! singleCheckbox && ! fields.has( key ) ) {
			fields.set( key, { key, label: plainText( attributes.label ) || EXTRA_FIELD_LABEL, section: 'extra' } );
		}
	} );

	return [ ...fields.values() ];
}

export const SECTION_LABELS = {
	billing: __( 'Billing', 'yaycheckout' ),
	shipping: __( 'Shipping', 'yaycheckout' ),
	extra: __( 'Extra', 'yaycheckout' ),
};

/**
 * The name each field goes by in a picker: its label and section, so Billing and Shipping
 * "First name" stay apart. A name two fields still share gets the key, so every name maps back to one field.
 */
export function fieldNames( fields: ShowableField[] ): Map< string, string > {
	const named = fields.map( ( field ) => {
		return { key: field.key, name: `${ field.label } (${ SECTION_LABELS[ field.section ] })` };
	} );

	const counts = new Map< string, number >();

	named.forEach( ( { name } ) => counts.set( name, ( counts.get( name ) ?? 0 ) + 1 ) );

	return new Map( named.map( ( { key, name } ) => [ key, ( counts.get( name ) ?? 0 ) > 1 ? `${ name } [${ key }]` : name ] ) );
}
