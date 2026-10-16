/**
 * Block visibility rules, for the storefront script and the editor preview. BlockVisibility in PHP owns
 * them and decides each block at render, and BlockEmptiness owns the empty markers; this copy re-decides
 * on step, cart and field changes.
 */

export type VisibilityRule =
	| { type: 'step'; step: number | 'last' }
	| { type: 'needs_shipping' }
	| { type: 'has_coupon' }
	| { type: 'subtotal'; op: 'gte' | 'lt'; amount: number }
	| { type: 'logged_in' }
	| { type: 'block'; block: string; is: 'empty' | 'not_empty' };

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
const RULE_TYPES: VisibilityRule[ 'type' ][] = [ 'step', 'needs_shipping', 'has_coupon', 'subtotal', 'logged_in', 'block' ];

/** Mirrors BlockEmptiness::SUPPORT in PHP. */
export const WATCHABLE_SUPPORT = 'yaycheckoutWatchable';

/** Mirrors BlockEmptiness::BLOCK_ID_PATTERN in PHP: 8 characters from [a-z0-9]. */
export const BLOCK_ID_PATTERN = /^[a-z0-9]{8}$/;

/** Mirrors BlockEmptiness::BLOCK_ID_ATTR in PHP. */
export const BLOCK_ID_ATTR = 'data-yaycheckout-block-id';

/** Mirrors BlockEmptiness::EMPTY_ATTR in PHP. */
export const EMPTY_ATTR = 'data-yaycheckout-empty';

/** Mirrors BlockEmptiness::LIVE_ATTR in PHP. */
export const LIVE_ATTR = 'data-yaycheckout-live';

/** Mirrors BlockEmptiness::LOCALE_ATTR in PHP. */
export const LOCALE_ATTR = 'data-yaycheckout-locale';

/** Mirrors BlockEmptiness::REFRESH_EVENT in PHP. */
export const REFRESH_EVENT = 'yaycheckout_visibility_refresh';

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

	if ( type === 'block' ) {
		const { block, is } = rule;

		if ( typeof block !== 'string' || ! BLOCK_ID_PATTERN.test( block ) || ( is !== 'empty' && is !== 'not_empty' ) ) {
			return null;
		}

		return { type, block, is };
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

		if ( ! RULE_TYPES.includes( type as VisibilityRule[ 'type' ] ) ) {
			return null;
		}

		const clean = normalizeRule( type as VisibilityRule[ 'type' ], rule as Record< string, unknown > );

		// Each type once, except "block", which may repeat for different blocks.
		if ( ! clean || rules.some( ( kept ) => ruleKey( kept ) === ruleKey( clean ) ) ) {
			return null;
		}

		rules.push( clean );
	}

	return { action: value.action, rules };
}

/** What makes a rule unique in a setting: its type, or for "block" its target. Mirrors normalize() in PHP. */
function ruleKey( rule: VisibilityRule ): string {
	return rule.type === 'block' ? `block:${ rule.block }` : rule.type;
}

/** The blockIds a setting's "block" rules point at. Mirrors BlockVisibility::block_ids() in PHP. */
export function blockIds( setting: VisibilitySetting ): string[] {
	return setting.rules.flatMap( ( rule ) => ( rule.type === 'block' ? [ rule.block ] : [] ) );
}

/**
 * The ids among ids that are empty under root: no element carries the id without EMPTY_ATTR, so an id no
 * element carries counts as empty. Mirrors BlockEmptiness::empty_ids() in PHP.
 */
export function emptyIds( root: ParentNode, ids: string[] ): string[] {
	return ids.filter( ( id ) => ! root.querySelector( `[${ BLOCK_ID_ATTR }="${ id }"]:not([${ EMPTY_ATTR }])` ) );
}

/** Mirrors BlockVisibility::matches() in PHP. */
function matches( rule: VisibilityRule, state: VisibilityState, step: number, stepCount: number, empty: string[] ): boolean {
	switch ( rule.type ) {
		case 'block':
			return empty.includes( rule.block ) === ( rule.is === 'empty' );
		case 'step':
			return rule.step === step || ( rule.step === 'last' && step === stepCount );
		case 'subtotal':
			return rule.op === 'gte' ? state.subtotal >= rule.amount : state.subtotal < rule.amount;
		default:
			return state[ rule.type ] === true;
	}
}

/** Mirrors BlockVisibility::is_visible() in PHP: without steps, step rules are skipped, and no rules left shows the block. */
export function isVisible(
	setting: VisibilitySetting,
	state: VisibilityState,
	step: number,
	stepCount: number,
	empty: string[] = []
): boolean {
	const rules = stepCount > 0 ? setting.rules : setting.rules.filter( ( rule ) => rule.type !== 'step' );

	if ( rules.length === 0 ) {
		return true;
	}

	return ( setting.action === 'show' ) === rules.every( ( rule ) => matches( rule, state, step, stepCount, empty ) );
}
