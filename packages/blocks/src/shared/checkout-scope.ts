import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { editorData } from './editor-data';

/** The layout's inputs.label token, as the merchant is editing it; '' for the default. */
export function useLabelMode(): 'floating' | 'hidden' | '' {
	const metaKey = editorData.design?.metaKey ?? '';

	return useSelect(
		( selectStore ) => {
			const meta = selectStore( editorStore ).getEditedPostAttribute( 'meta' ) as
				| Record< string, { inputs?: { label?: unknown } } | undefined >
				| undefined;

			const label = '' !== metaKey ? meta?.[ metaKey ]?.inputs?.label : undefined;

			return 'floating' === label || 'hidden' === label ? label : '';
		},
		[ metaKey ]
	);
}

/**
 * The classes a block that may sit outside Checkout puts on its wrapper, so the checkout styles reach it.
 * Mirrors CheckoutScope::classes() in PHP.
 */
export function useScopeClass(): string {
	return 'yaycheckout-scope' + ( 'floating' === useLabelMode() ? ' yaycheckout-scope--floating-labels' : '' );
}
