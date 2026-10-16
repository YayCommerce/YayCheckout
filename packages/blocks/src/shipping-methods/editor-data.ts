import type { MethodSetupData } from '../shared/setup-notice';

declare global {
	interface Window {
		yayCheckoutShippingSetup?: MethodSetupData;
	}
}

/**
 * Values BlockRegistry prints before this bundle runs.
 * needsSetup mirrors CheckoutSetupHelper::shipping_needs_setup() in PHP.
 */
export const shippingSetupData: MethodSetupData = window.yayCheckoutShippingSetup ?? {
	needsSetup: false,
	settingsUrl: '',
};
