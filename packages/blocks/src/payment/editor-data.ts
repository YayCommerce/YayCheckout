import type { MethodSetupData } from '../shared/setup-notice';

declare global {
	interface Window {
		yayCheckoutPaymentSetup?: MethodSetupData;
	}
}

/**
 * Values BlockRegistry prints before this bundle runs.
 * needsSetup mirrors CheckoutSetupHelper::payment_needs_setup() in PHP.
 */
export const paymentSetupData: MethodSetupData = window.yayCheckoutPaymentSetup ?? {
	needsSetup: false,
	settingsUrl: '',
};
