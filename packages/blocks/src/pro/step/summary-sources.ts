/**
 * Fields inside one Step that the completed-step summary can leave out.
 * Layout-wide showable-fields stays for Field Value; this list is this step only and keeps single checkboxes.
 */

import { __ } from '@wordpress/i18n';
import couponMeta from '../../coupon-form/block.json';
import createAccountMeta from '../../create-account/block.json';
import differentAddressMeta from '../../different-address/block.json';
import emailMeta from '../../email/block.json';
import { EMAIL_LABEL } from '../../email/defaults';
import extraFieldMeta from '../../extra-field/block.json';
import { EXTRA_FIELD_LABEL } from '../../extra-field/defaults';
import fieldMeta from '../../field/block.json';
import orderNoteMeta from '../../order-note/block.json';
import { ORDER_NOTE_LABEL } from '../../order-note/defaults';
import paymentMeta from '../../payment/block.json';
import type { BlockEditorSelectors } from '../../shared/section-fields';
import { plainText } from '../../shared/plain-text';
import shippingMethodsMeta from '../../shipping-methods/block.json';
import termsMeta from '../../terms/block.json';

export interface SummarySource {
	key: string;
	label: string;
}

const SHIP_LABEL = __( 'Ship to a different address?', 'yaycheckout' );
const CREATE_ACCOUNT_LABEL = __( 'Create an account?', 'yaycheckout' );
const ACCOUNT_USERNAME_LABEL = __( 'Username', 'yaycheckout' );
const ACCOUNT_PASSWORD_LABEL = __( 'Password', 'yaycheckout' );
const TERMS_LABEL = __( 'Terms and conditions', 'yaycheckout' );
const COUPON_LABEL = __( 'Coupon code', 'yaycheckout' );
const SHIPPING_METHODS_LABEL = __( 'Shipping methods', 'yaycheckout' );
const PAYMENT_LABEL = __( 'Payment', 'yaycheckout' );

/** One entry per summary-able block under stepClientId, in document order. */
export function summarySources( editor: BlockEditorSelectors, stepClientId: string ): SummarySource[] {
	const attributesOf = ( clientId: string ) => editor.getBlockAttributes( clientId ) ?? {};

	return editor.getClientIdsOfDescendants( [ stepClientId ] ).flatMap( ( clientId ): SummarySource[] => {
		const name = editor.getBlockName( clientId );
		const attributes = attributesOf( clientId );

		if ( name === emailMeta.name ) {
			return [ { key: 'billing_email', label: plainText( attributes.label ) || EMAIL_LABEL } ];
		}

		if ( name === fieldMeta.name || name === extraFieldMeta.name ) {
			const key = String( attributes.fieldKey ?? '' ).trim();

			if ( key === '' ) {
				return [];
			}

			return [
				{
					key,
					label: plainText( attributes.label ) || ( name === extraFieldMeta.name ? EXTRA_FIELD_LABEL : key ),
				},
			];
		}

		if ( name === orderNoteMeta.name ) {
			return [
				{
					key: 'order_comments',
					label: plainText( attributes.disclosureText ) || plainText( attributes.label ) || ORDER_NOTE_LABEL,
				},
			];
		}

		if ( name === differentAddressMeta.name ) {
			return [
				{
					key: 'ship_to_different_address',
					label:
						plainText( attributes.shipLabel ) ||
						plainText( attributes.sameShipLabel ) ||
						plainText( attributes.sameLabel ) ||
						SHIP_LABEL,
				},
			];
		}

		if ( name === createAccountMeta.name ) {
			return [
				{
					key: 'createaccount',
					label: plainText( attributes.checkboxLabel ) || CREATE_ACCOUNT_LABEL,
				},
				{ key: 'account_username', label: ACCOUNT_USERNAME_LABEL },
				{ key: 'account_password', label: ACCOUNT_PASSWORD_LABEL },
			];
		}

		if ( name === termsMeta.name ) {
			return [ { key: 'terms', label: TERMS_LABEL } ];
		}

		if ( name === shippingMethodsMeta.name ) {
			return [
				{
					key: 'shipping_method',
					label: plainText( attributes.label ) || SHIPPING_METHODS_LABEL,
				},
			];
		}

		if ( name === paymentMeta.name ) {
			return [
				{
					key: 'payment_method',
					label: plainText( attributes.label ) || PAYMENT_LABEL,
				},
			];
		}

		if ( name === couponMeta.name ) {
			return [ { key: 'coupon_code', label: plainText( attributes.placeholder ) || COUPON_LABEL } ];
		}

		return [];
	} );
}

/**
 * The name each source goes by in the omit picker. A name two sources still share gets the key,
 * so every name maps back to one source.
 */
export function summarySourceNames( sources: SummarySource[] ): Map< string, string > {
	const named = sources.map( ( source ) => ( { key: source.key, name: source.label } ) );
	const counts = new Map< string, number >();

	named.forEach( ( { name } ) => counts.set( name, ( counts.get( name ) ?? 0 ) + 1 ) );

	return new Map(
		named.map( ( { key, name } ) => [ key, ( counts.get( name ) ?? 0 ) > 1 ? `${ name } [${ key }]` : name ] )
	);
}
