import { decodeEntities } from '@wordpress/html-entities';

/** Mirrors LayoutMarkupReader::plain_text(): tags stripped before entities are decoded, then trimmed. */
export function plainText( value: unknown ): string {
	return decodeEntities( String( value ?? '' ).replace( /<[^>]*>/g, '' ) ).trim();
}
