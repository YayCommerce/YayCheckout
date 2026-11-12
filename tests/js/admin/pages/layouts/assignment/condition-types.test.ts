import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { describe, expect, test, vi } from 'vitest';

vi.mock( '@wordpress/i18n', () => ( { __: ( text: string ) => text } ) );

const {
	CONDITION_TYPES,
	groupsFromWire,
	groupsToWire,
	isConditionComplete,
	isKnownType,
	newCondition,
} = await import( '../../../../../../apps/admin/src/pages/layouts/assignment/condition-types' );

type Definition = { operators: string[]; value: string; choices?: string[] };

function phpDefinitions(): Record< string, Definition > {
	const file = fileURLToPath( new URL( '../../../../../../includes/Enums/ConditionType.php', import.meta.url ) );
	const code = `define('ABSPATH', 1); require '${ file }'; echo json_encode(YayCheckout\\Enums\\ConditionType::definitions());`;

	return JSON.parse( execFileSync( 'php', [ '-r', code ] ).toString() );
}

describe( 'condition-types @baseline', () => {
	test( 'offers the types, operators and choices Enums\\ConditionType accepts', () => {
		const php = phpDefinitions();

		expect( Object.keys( CONDITION_TYPES ) ).toEqual( Object.keys( php ) );
		for ( const [ type, definition ] of Object.entries( php ) ) {
			const ts = CONDITION_TYPES[ type as keyof typeof CONDITION_TYPES ];

			expect( ts.operators.map( ( o ) => o.value ), type ).toEqual( definition.operators );
			expect( ts.choices?.map( ( c ) => c.value ), type ).toEqual( definition.choices );
			expect( Boolean( ts.ids ), type ).toBe( 'ids' === definition.value );
			expect( ts.number ?? null, type ).toBe( [ 'amount', 'count' ].includes( definition.value ) ? definition.value : null );
		}
	} );

	test( 'a new condition starts on the first operator with an empty value', () => {
		expect( newCondition( 'login_status' ) ).toMatchObject( { operator: 'is', value: '' } );
		expect( newCondition( 'product' ) ).toMatchObject( { operator: 'contains_any', value: [] } );
		expect( newCondition( 'cart_subtotal' ) ).toMatchObject( { operator: 'at_least', value: '' } );
	} );

	test( 'only a known type with a value is complete', () => {
		expect( isConditionComplete( { id: '1', type: 'product', operator: 'contains_any', value: [ '5' ] } ) ).toBe( true );
		expect( isConditionComplete( { id: '1', type: 'product', operator: 'contains_any', value: [] } ) ).toBe( false );
		expect( isConditionComplete( { id: '1', type: 'cart_subtotal', operator: 'at_least', value: '' } ) ).toBe( false );
		expect( isConditionComplete( { id: '1', type: 'bogus' as never, operator: 'is', value: [ 'x' ] } ) ).toBe( false );
		expect( isKnownType( '__proto__' ) ).toBe( false );
	} );

	test( 'form state round-trips to the wire with ids and numbers as numbers', () => {
		const wire = [
			[
				{ type: 'product', operator: 'contains_any', value: [ 1, 2 ] },
				{ type: 'cart_subtotal', operator: 'at_least', value: 19.5 },
				{ type: 'customer_role', operator: 'in', value: [ 'customer' ] },
				{ type: 'retired_type', operator: 'is', value: [ 'x' ] },
			],
		];
		const groups = groupsFromWire( wire as never );

		expect( groups[ 0 ].conditions.map( ( c ) => c.value ) ).toEqual( [ [ '1', '2' ], '19.5', [ 'customer' ], [ 'x' ] ] );
		expect( groupsToWire( groups ) ).toEqual( wire );

		groups[ 0 ].conditions[ 1 ].value = '';
		expect( groupsToWire( groups )[ 0 ][ 1 ].value ).toBe( '' );
	} );
} );
