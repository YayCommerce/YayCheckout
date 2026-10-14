/**
 * Block visibility rules, for the storefront script and the editor preview. BlockVisibility in PHP owns
 * them and decides each block at render; this copy re-decides on step and cart changes.
 */

export type VisibilityRule =
	| { type: 'step'; step: number | 'last' }
	| { type: 'needs_shipping' }
	| { type: 'has_coupon' }
	| { type: 'subtotal'; op: 'gte' | 'lt'; amount: number }
	| { type: 'logged_in' };

export type VisibilitySetting = { action: 'show' | 'hide'; rules: VisibilityRule[] };

/** As BlockVisibility::state() prints it; subtotal is already rounded to the store's decimals. */
export type VisibilityState = { needs_shipping: boolean; has_coupon: boolean; subtotal: number; logged_in: boolean };

/** Mirrors BlockVisibility::ATTRIBUTE in PHP. */
export const ATTRIBUTE = 'yaycheckoutVisibility';

/** Mirrors BlockVisibility::EXCLUDED in PHP. */
export const EXCLUDED: string[] = [ 'yaycheckout/checkout', 'yaycheckout/steps', 'yaycheckout/step' ];

/** Mirrors BlockVisibility::HIDDEN_CLASS in PHP. */
export const HIDDEN_CLASS = 'yaycheckout-hidden-by-visibility';

/** Mirrors BlockVisibility::STATE_ID in PHP. */
export const STATE_ID = 'yaycheckout-visibility-state';

/** Mirrors BlockVisibility::DATA_ATTR in PHP. */
export const DATA_ATTR = 'data-yaycheckout-visibility';

/** Mirrors BlockVisibility::RULE_TYPES in PHP. */
const RULE_TYPES: VisibilityRule[ 'type' ][] = [ 'step', 'needs_shipping', 'has_coupon', 'subtotal', 'logged_in' ];

function isRecord( value: unknown ): value is Record< string, unknown > {
	return value !== null && typeof value === 'object' && ! Array.isArray( value );
}

/** Mirrors BlockVisibility::normalize_rule() in PHP. */
function normalizeRule( type: VisibilityRule[ 'type' ], rule: Record< string, unknown > ): VisibilityRule | null {
	if ( type === 'step' ) {
		if ( rule.step === 'last' ) {
			return { type, step: 'last' };
		}

		return typeof rule.step === 'number' && Number.isInteger( rule.step ) && rule.step >= 1 ? { type, step: rule.step } : null;
	}

	if ( type === 'subtotal' ) {
		const { op, amount } = rule;

		if ( ( op !== 'gte' && op !== 'lt' ) || typeof amount !== 'number' || ! Number.isFinite( amount ) || amount < 0 ) {
			return null;
		}

		return { type, op, amount };
	}

	return { type };
}

/** Mirrors BlockVisibility::normalize() in PHP. */
export function normalize( value: unknown ): VisibilitySetting | null {
	if ( ! isRecord( value ) || ( value.action !== 'show' && value.action !== 'hide' ) ) {
		return null;
	}

	if ( ! Array.isArray( value.rules ) || value.rules.length === 0 ) {
		return null;
	}

	const rules: VisibilityRule[] = [];

	for ( const rule of value.rules as unknown[] ) {
		const type = isRecord( rule ) ? rule.type : undefined;

		if ( ! RULE_TYPES.includes( type as VisibilityRule[ 'type' ] ) || rules.some( ( kept ) => kept.type === type ) ) {
			return null;
		}

		const clean = normalizeRule( type as VisibilityRule[ 'type' ], rule as Record< string, unknown > );

		if ( ! clean ) {
			return null;
		}

		rules.push( clean );
	}

	return { action: value.action, rules };
}

/** Mirrors BlockVisibility::matches() in PHP. */
function matches( rule: VisibilityRule, state: VisibilityState, step: number, stepCount: number ): boolean {
	switch ( rule.type ) {
		case 'step':
			return stepCount > 0 && ( rule.step === step || ( rule.step === 'last' && step === stepCount ) );
		case 'subtotal':
			return rule.op === 'gte' ? state.subtotal >= rule.amount : state.subtotal < rule.amount;
		default:
			return state[ rule.type ] === true;
	}
}

/** Mirrors BlockVisibility::is_visible() in PHP. */
export function isVisible( setting: VisibilitySetting, state: VisibilityState, step: number, stepCount: number ): boolean {
	return ( setting.action === 'show' ) === setting.rules.every( ( rule ) => matches( rule, state, step, stepCount ) );
}
