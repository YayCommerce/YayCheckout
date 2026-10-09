import { editorData, type DesignTokens } from './editor-data';

/** Mirrors DesignStyles::FONT_FALLBACK in PHP. */
export const FONT_FALLBACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

/** Mirrors DesignStyles::INPUT_HEIGHTS in PHP. */
export const INPUT_HEIGHTS: Record< string, number > = { small: 32, medium: 40, large: 48 };

/**
 * Mirrors DesignStyles::vars() in PHP: custom property values by name, without the `--yaycheckout-` prefix.
 * Unlike PHP it names every property, with `initial` where PHP prints nothing (a section at theme default,
 * no font picked), so the live canvas style also clears what the saved design printed.
 */
export function designVars( tokens: DesignTokens ): Record< string, string > {
	const { colours, typography, inputs } = tokens;
	const isUnderline = inputs.style === 'underline';
	const font = typography.enabled ? editorData.design?.font_families[ String( typography.font_family ) ] : '';

	return {
		'color-primary': colours.enabled ? String( colours.primary ) : 'initial',
		'color-text': colours.enabled ? String( colours.text ) : 'initial',
		'color-background': colours.enabled ? String( colours.background ) : 'initial',
		'color-border': colours.enabled ? String( colours.border ) : 'initial',
		'font-family': font ? `"${ font }", ${ FONT_FALLBACK }` : 'initial',
		'font-size-base': typography.enabled ? `${ typography.base_size }px` : 'initial',
		'input-radius': ! inputs.enabled ? 'initial' : isUnderline ? '0px' : `${ inputs.radius }px`,
		'input-height': inputs.enabled ? `${ INPUT_HEIGHTS[ String( inputs.size ) ] }px` : 'initial',
		'input-border-width': ! inputs.enabled ? 'initial' : isUnderline ? '0 0 1px' : '1px',
		'input-padding-inline': ! inputs.enabled ? 'initial' : isUnderline ? '0' : '12px',
	};
}
