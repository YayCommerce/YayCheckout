import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useCanvasStyle } from '../shared/canvas-style';
import type { BlockEditorSelectors } from '../shared/section-fields';
import { showableFields } from '../shared/showable-fields';
import { FIELD_PREFIX, KEY_ATTR, LIVE_VALUE_CLASS } from './constants';

const STYLE_ID = 'yaycheckout-live-value-missing';

/** text as a double-quoted CSS string. */
function cssString( text: string ): string {
	return `"${ text.replace( /[\\"]/g, '\\$&' ).replace( /\n/g, '\\a ' ) }"`;
}

/**
 * Marks each field chip whose field is no longer a showable field of the layout, which the storefront prints
 * as empty. The chip stays in the text; the merchant removes it or adds the field back. A chip's text can't
 * be changed from outside the rich text, so the warning is a canvas style: every field chip is marked, then
 * each current field's chips are unmarked.
 */
function MissingFields() {
	// A string, so the style is rebuilt only when the layout's fields change, not on every store change.
	const keysJson = useSelect(
		( select ) =>
			JSON.stringify( showableFields( select( blockEditorStore ) as BlockEditorSelectors ).map( ( field ) => field.key ) ),
		[]
	);

	const css = useMemo( () => {
		const chip = `.${ LIVE_VALUE_CLASS }[${ KEY_ATTR }`;
		const keys = JSON.parse( keysJson ) as string[];
		const current = keys.map( ( key ) => `${ chip }=${ cssString( FIELD_PREFIX + key ) }]` );
		/* translators: Shown after a live value whose field is no longer in the layout. */
		const missing = cssString( ` – ${ __( 'Missing field', 'yaycheckout' ) }` );

		return [
			`${ chip }^="${ FIELD_PREFIX }"] { color: var(--yaycheckout-live-value-warning); }`,
			`${ chip }^="${ FIELD_PREFIX }"]::after { content: ${ missing }; }`,
			...( current.length
				? [
						`${ current.join( ', ' ) } { color: inherit; }`,
						`${ current.map( ( selector ) => `${ selector }::after` ).join( ', ' ) } { content: none; }`,
				  ]
				: [] ),
		].join( '\n' );
	}, [ keysJson ] );

	useCanvasStyle( css, STYLE_ID );

	return null;
}

registerPlugin( 'yaycheckout-live-value-missing', { render: MissingFields } );
