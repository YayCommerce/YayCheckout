export type ShipToDestination = 'billing' | 'shipping' | 'billing_only';

type DifferentAddressEditorData = { destination: ShipToDestination; copiedKeys: string[]; settingsUrl: string };

declare global {
	interface Window {
		yayCheckoutDifferentAddress?: DifferentAddressEditorData;
	}
}

/**
 * Values BlockRegistry prints before this bundle runs.
 * destination is CheckoutDifferentAddress::destination(), and copiedKeys its COPIED_KEYS.
 */
export const differentAddressEditorData: DifferentAddressEditorData = window.yayCheckoutDifferentAddress ?? {
	destination: 'billing',
	copiedKeys: [],
	settingsUrl: '',
};
