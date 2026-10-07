type EditorData = {
	isPro: boolean;
	postType: string;
	upgradeUrl: string;
	/** Billing fields WooCommerce requires before the layout's overlay, for the store's base country. */
	requiredByWooCommerce: Array< { key: string; label: string } >;
	/** RequiredIndicator::optional_text(): the "optional" fallback, translated. */
	optionalText: string;
};

declare global {
	interface Window {
		yayCheckoutBlocks?: EditorData;
	}
}

/** Values BlockRegistry prints before this bundle runs; PHP owns every one of them. */
export const editorData: EditorData = window.yayCheckoutBlocks ?? {
	isPro: false,
	postType: '',
	upgradeUrl: '',
	requiredByWooCommerce: [],
	optionalText: 'optional',
};
