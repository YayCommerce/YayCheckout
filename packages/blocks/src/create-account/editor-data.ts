type CreateAccountEditorData = { signupEnabled: boolean; settingsUrl: string };

declare global {
	interface Window {
		yayCheckoutCreateAccount?: CreateAccountEditorData;
	}
}

/**
 * Values BlockRegistry prints before this bundle runs.
 * signupEnabled mirrors woocommerce_enable_signup_and_login_from_checkout.
 */
export const createAccountEditorData: CreateAccountEditorData = window.yayCheckoutCreateAccount ?? {
	signupEnabled: false,
	settingsUrl: '',
};
