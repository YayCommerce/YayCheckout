type EditorData = { isPro: boolean; postType: string; upgradeUrl: string };

declare global {
	interface Window {
		yayCheckoutBlocks?: EditorData;
	}
}

/** Values BlockRegistry prints before this bundle runs; PHP owns every one of them. */
export const editorData: EditorData = window.yayCheckoutBlocks ?? { isPro: false, postType: '', upgradeUrl: '' };
