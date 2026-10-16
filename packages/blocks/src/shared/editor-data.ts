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
	/** The fontFamilies slugs whose fallback is a serif stack. */
	serifFonts: string[];
	/** The fontFamilies slugs the device already has => the stack they print, with no fallback added. */
	fontStacks: Record< string, string >;
	/** The id of the canvas's printed font faces, which the sidebar rewrites when the merchant picks a font. */
	fontStyleId: string;
	customCssMaxBytes: number;
};

/** The layout editor's view switch, from BlockRegistry::editor_design_data(). */
export type EditorView = {
	/** LayoutEditorEmbed::VIEW_MAIN or VIEW_LOGGED_OUT; the latter edits the markup a guest who must log in sees (Layout::META_LOGGED_OUT). */
	current: 'main' | 'logged-out';
	/** CheckoutLogin::login_required(): WooCommerce makes guests log in before checkout. */
	loginRequired: boolean;
	/** LayoutEditorEmbed::QUERY_VIEW. */
	queryArg: string;
	/** The embedded editor for each view of this layout: LayoutEditorEmbed::view_urls(). */
	urls: Record< 'main' | 'logged-out', string >;
};

type EditorData = {
	isPro: boolean;
	/** BlockRegistry::locked_blocks(): the Pro blocks this site hasn't registered, which the editor offers as locked stubs. */
	lockedBlocks: string[];
	postType: string;
	upgradeUrl: string;
	/** The store's currency symbol, decoded, for amounts the merchant types (the Visibility panel's subtotal). */
	currencySymbol: string;
	design: EditorDesign | null;
	/** Cart value key => label, from LiveValues::cart_values(); printed in the block editor only. */
	liveValues: Record< string, string >;
	view: EditorView;
};

declare global {
	interface Window {
		yayCheckoutBlocks?: Partial< EditorData >;
	}
}

/** Values BlockRegistry prints before this bundle runs; PHP owns every one of them. */
export const editorData: EditorData = {
	isPro: false,
	lockedBlocks: [],
	postType: '',
	upgradeUrl: '',
	currencySymbol: '',
	design: null,
	liveValues: {},
	view: { current: 'main', loginRequired: false, queryArg: '', urls: { main: '', 'logged-out': '' } },
	...window.yayCheckoutBlocks,
};
