/**
 * Old Shipping Methods and Payment layout values => their current names. Saved layouts keep
 * the old values, so both blocks read them through this. Same map as CheckoutFragments::LEGACY_METHOD_LAYOUTS.
 */
export const LEGACY_METHOD_LAYOUTS: Record< string, string > = {
	default: 'joined',
	check: 'checkmark',
	boxed: 'box',
	columns: 'grid',
	stacked: 'cards',
	divided: 'lines',
};

/** The current name for a stored layout value; unknown values pass through. */
export function currentMethodLayout( value: string | undefined ): string | undefined {
	return ( value && LEGACY_METHOD_LAYOUTS[ value ] ) || value;
}
