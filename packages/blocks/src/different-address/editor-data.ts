export type ShipToDestination = 'billing' | 'shipping' | 'billing_only';

type DifferentAddressEditorData = { destination: ShipToDestination; settingsUrl: string };

declare global {
	interface Window {
		yayCheckoutDifferentAddress?: DifferentAddressEditorData;
	}
}

/**
 * Values BlockRegistry prints before this bundle runs.
 * destination mirrors CheckoutDifferentAddress::destination() in PHP.
 */
export const differentAddressEditorData: DifferentAddressEditorData = window.yayCheckoutDifferentAddress ?? {
	destination: 'billing',
	settingsUrl: '',
};
