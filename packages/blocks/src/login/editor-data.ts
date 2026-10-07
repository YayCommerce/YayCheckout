type LoginEditorData = { reminderEnabled: boolean; settingsUrl: string };

declare global {
	interface Window {
		yayCheckoutLogin?: LoginEditorData;
	}
}

/**
 * Values BlockRegistry prints before this bundle runs.
 * reminderEnabled mirrors CheckoutLogin::OPTION_REMINDER in PHP.
 */
export const loginEditorData: LoginEditorData = window.yayCheckoutLogin ?? {
	reminderEnabled: false,
	settingsUrl: '',
};
