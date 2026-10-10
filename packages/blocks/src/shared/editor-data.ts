/** One LayoutDesign::schema() rule. */
export type DesignRule =
	/** Empty is null, so 0 stays a value. */
	| { type: 'int'; default: null; min: number; max: number }
	/** '' is one of the members: Theme default. */
	| { type: 'enum'; default: string; enum: string[] }
	/** A layout's colour: unset (''), a hex colour or `var(--wp--preset--color--…)`. */
	| { type: 'color'; default: string }
	/** A layout's background: any `color`, or a gradient, written out or `var(--wp--preset--gradient--…)`. */
	| { type: 'fill'; default: string };

/** Section => token => value, as LayoutDesign stores them (snake_case keys). An empty token is '' or null. */
export type DesignTokens = Record< string, Record< string, string | number | null > >;

/** What the Layout design sidebar edits the layout's design with: LayoutDesign's rules. */
export type EditorDesign = {
	metaKey: string;
	/** The id of the canvas's printed design style, which the sidebar rewrites as the merchant edits. */
	styleId: string;
	schema: Record< string, Record< string, DesignRule > >;
	/** Slug => CSS font-family name; '' is the theme's font. */
	fontFamilies: Record< string, string >;
	customCssMaxBytes: number;
};

type EditorData = { isPro: boolean; postType: string; upgradeUrl: string; design: EditorDesign | null };

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
	design: null,
};
