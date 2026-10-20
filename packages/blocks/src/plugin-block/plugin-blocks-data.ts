import { jsonObjectAt } from '../shared/load-editor-styles';

/** Where a spot's plugin stands on this site: PluginBlockCatalogue::editor_data()'s states. */
export type PluginBlockState = 'not_installed' | 'inactive' | 'unlicensed' | 'feature_off' | 'shortcode' | 'unavailable';

/** One PluginBlockCatalogue entry, as PluginBlockCatalogue::editor_data() prints it. */
export type PluginBlockEntry = {
	target: string;
	/** IntegrationsHelper's slug, e.g. yayboost: one install or activate moves every entry of the plugin. */
	plugin: string;
	pluginName: string;
	iconUrl: string | null;
	pluginFile: string;
	wporgSlug: string;
	title: string;
	pitch: string;
	/** The plugin's settings for the block's feature; '' for none. */
	settingsUrl: string;
	learnMoreUrl: string;
	/** The plugin's id for the block's feature, e.g. order_bump; '' for none. */
	featureId: string;
	/** The shortcode that fills the spot (state `shortcode`); '' for none. */
	shortcode: string;
	/** Where the plugin's license is entered (state `unlicensed`). */
	licenseUrl: string;
	installUrl: string;
	pluginsUrl: string;
	/** False while the plugin has no such block yet. */
	available: boolean;
	/** The WooCommerce hooks the plugin prints the feature on in a layout without a spot; HookOutputPreview stands in there. */
	hooks: string[];
	/** `<folder>/<file>.php`, or null while not installed. */
	pluginPath: string | null;
	state: PluginBlockState;
};

type PluginBlocksData = { canInstall: boolean; canActivate: boolean; entries: PluginBlockEntry[] };

declare global {
	interface Window {
		yayCheckoutPluginBlocks?: PluginBlocksData;
	}
}

/** BlockRegistry::editor_plugin_blocks_data(), printed in the layout editor only; empty anywhere else. */
export const pluginBlocksData: PluginBlocksData = window.yayCheckoutPluginBlocks ?? {
	canInstall: false,
	canActivate: false,
	entries: [],
};

const VARIABLE = 'window.yayCheckoutPluginBlocks = ';

/**
 * The catalogue a freshly loaded editor prints, read from `page` (loadEditorStyles' fetch): the server's states
 * after an install or activate, which the client can't tell apart (a Free plugin's shortcode, an unlicensed Pro).
 * Null when the page doesn't print it.
 */
export function pluginBlocksDataFrom( page: Document ): PluginBlocksData | null {
	for ( const script of Array.from( page.querySelectorAll( 'script:not([src])' ) ) ) {
		const text = script.textContent ?? '';
		const at = text.indexOf( VARIABLE );

		if ( at !== -1 ) {
			return jsonObjectAt( text, at + VARIABLE.length ) as unknown as PluginBlocksData | null;
		}
	}

	return null;
}
