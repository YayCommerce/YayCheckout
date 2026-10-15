type ExpressPayEditorData = { hasButtons: boolean; settingsUrl: string };

declare global {
	interface Window {
		yayCheckoutExpressPay?: ExpressPayEditorData;
	}
}

/**
 * Values BlockRegistry prints before this bundle runs.
 * hasButtons mirrors ExpressPay::has_buttons() in PHP.
 */
export const expressPayEditorData: ExpressPayEditorData = window.yayCheckoutExpressPay ?? {
	hasButtons: true,
	settingsUrl: '',
};
