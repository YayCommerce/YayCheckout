import { describe, expect, test } from 'vitest';
import {
	ATTRIBUTE,
	BLOCK_ID_ATTR,
	DATA_ATTR,
	EMPTY_ATTR,
	EXCLUDED,
	HIDDEN_CLASS,
	LIVE_ATTR,
	LOCALE_ATTR,
	REFRESH_EVENT,
	STATE_ID,
	WATCHABLE_SUPPORT,
	isVisible,
	normalize,
	type VisibilityState,
} from '../../../../packages/blocks/src/shared/visibility-rules';
import { phpConst } from '../../helpers/php-const';

const STATE: VisibilityState = { needs_shipping: false, has_coupon: true, subtotal: 50, logged_in: false };

describe( 'visibility-rules @baseline', () => {
	test( 'normalize keeps a valid setting and rejects malformed rules', () => {
		const setting = { action: 'show', rules: [ { type: 'step', step: 'last' }, { type: 'subtotal', op: 'gte', amount: 10 } ] };

		expect( normalize( setting ) ).toEqual( setting );
		expect( normalize( { action: 'show', rules: [] } ) ).toBeNull();
		expect( normalize( { action: 'show', rules: [ { type: 'step', step: 0 } ] } ) ).toBeNull();
		expect( normalize( { action: 'show', rules: [ { type: 'step', step: 1.5 } ] } ) ).toBeNull();
		expect( normalize( { action: 'show', rules: [ { type: 'subtotal', op: 'gte', amount: -1 } ] } ) ).toBeNull();
		expect( normalize( { action: 'show', rules: [ { type: 'block', block: 'ABC', is: 'empty' } ] } ) ).toBeNull();
		expect( normalize( { action: 'show', rules: [ { type: 'bogus' } ] } ) ).toBeNull();
	} );

	test( 'normalize allows repeated block rules but no other repeated type', () => {
		expect(
			normalize( {
				action: 'hide',
				rules: [
					{ type: 'block', block: 'abcd1234', is: 'empty' },
					{ type: 'block', block: 'wxyz9876', is: 'not_empty' },
				],
			} )
		).not.toBeNull();
		expect( normalize( { action: 'hide', rules: [ { type: 'has_coupon' }, { type: 'has_coupon' } ] } ) ).toBeNull();
	} );

	test( 'isVisible matches the last step, and skips step rules on a checkout without steps', () => {
		const lastStep = { action: 'show' as const, rules: [ { type: 'step' as const, step: 'last' as const } ] };

		expect( isVisible( lastStep, STATE, 3, 3 ) ).toBe( true );
		expect( isVisible( lastStep, STATE, 2, 3 ) ).toBe( false );
		expect( isVisible( lastStep, STATE, 0, 0 ) ).toBe( true );
	} );

	test( 'isVisible needs every rule to match, with the subtotal bound inclusive for gte', () => {
		const hide = { action: 'hide' as const, rules: [ { type: 'has_coupon' as const }, { type: 'logged_in' as const } ] };

		expect( isVisible( hide, STATE, 0, 0 ) ).toBe( true );
		expect( isVisible( { action: 'show', rules: [ { type: 'subtotal', op: 'gte', amount: 50 } ] }, STATE, 0, 0 ) ).toBe( true );
		expect( isVisible( { action: 'show', rules: [ { type: 'subtotal', op: 'lt', amount: 50 } ] }, STATE, 0, 0 ) ).toBe( false );
		expect(
			isVisible( { action: 'show', rules: [ { type: 'block', block: 'abcd1234', is: 'empty' } ] }, STATE, 0, 0, [ 'abcd1234' ] )
		).toBe( true );
	} );

	test( 'constants mirror BlockVisibility and BlockEmptiness in PHP', () => {
		const visibility = 'includes/Renderer/BlockVisibility.php';
		const emptiness = 'includes/Renderer/BlockEmptiness.php';

		expect( ATTRIBUTE ).toBe( phpConst( visibility, 'ATTRIBUTE' ) );
		expect( DATA_ATTR ).toBe( phpConst( visibility, 'DATA_ATTR' ) );
		expect( HIDDEN_CLASS ).toBe( phpConst( visibility, 'HIDDEN_CLASS' ) );
		expect( STATE_ID ).toBe( phpConst( visibility, 'STATE_ID' ) );
		expect( EXCLUDED ).toEqual( phpConst( visibility, 'EXCLUDED' ) );
		expect( WATCHABLE_SUPPORT ).toBe( phpConst( emptiness, 'SUPPORT' ) );
		expect( BLOCK_ID_ATTR ).toBe( phpConst( emptiness, 'BLOCK_ID_ATTR' ) );
		expect( EMPTY_ATTR ).toBe( phpConst( emptiness, 'EMPTY_ATTR' ) );
		expect( LIVE_ATTR ).toBe( phpConst( emptiness, 'LIVE_ATTR' ) );
		expect( LOCALE_ATTR ).toBe( phpConst( emptiness, 'LOCALE_ATTR' ) );
		expect( REFRESH_EVENT ).toBe( phpConst( emptiness, 'REFRESH_EVENT' ) );
	} );

	test( 'normalize accepts exactly the PHP rule types', () => {
		const types = phpConst( 'includes/Renderer/BlockVisibility.php', 'RULE_TYPES' ) as string[];
		const sample: Record< string, object > = {
			step: { step: 1 },
			subtotal: { op: 'gte', amount: 1 },
			block: { block: 'abcd1234', is: 'empty' },
		};

		expect( types ).toEqual( [ 'step', 'needs_shipping', 'has_coupon', 'subtotal', 'logged_in', 'block' ] );
		for ( const type of types ) {
			expect( normalize( { action: 'show', rules: [ { type, ...sample[ type ] } ] } ), type ).not.toBeNull();
		}
	} );
} );
