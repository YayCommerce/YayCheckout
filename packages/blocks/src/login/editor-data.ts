type LoginEditorData = {
	reminderEnabled: boolean;
	settingsUrl: string;
	/** Layout::GUEST_VIEW_POST_TYPE: in its editor a Login always prints. */
	guestPostType: string;
};

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
	guestPostType: '',
};
