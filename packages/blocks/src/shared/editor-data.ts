/** One token's rule from Design::schema(). */
export type TokenRule = {
	type: 'bool' | 'int' | 'enum' | 'hex_color';
	default: boolean | number | string;
	enum?: string[];
	min?: number;
	max?: number;
};

export type TokenValue = boolean | number | string;

/** section => key => value, as Design::$tokens holds them. */
export type DesignTokens = Record< string, Record< string, TokenValue > >;

/** Pro only: what the layout Design sidebar needs from PHP (ProServiceProvider::editor_data()). */
export type EditorDesignData = {
	tokens: DesignTokens;
	schema: Record< string, Record< string, TokenRule > >;
	font_families: Record< string, string >;
};

type EditorData = { isPro: boolean; postType: string; upgradeUrl: string; design?: EditorDesignData };

declare global {
	interface Window {
		yayCheckoutBlocks?: EditorData;
	}
}

/** Values BlockRegistry prints before this bundle runs; PHP owns every one of them. */
export const editorData: EditorData = window.yayCheckoutBlocks ?? { isPro: false, postType: '', upgradeUrl: '' };
