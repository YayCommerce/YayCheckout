import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import detailsMeta from '../order-summary-details/block.json';
import headerMeta from '../order-summary-header/block.json';
import toggleMeta from '../order-summary-toggle/block.json';
import metadata from './block.json';

/** Whether an Order Summary with these direct children prints a header and details instead of its heading. */
export function isNewShape( childNames: string[] ): boolean {
	return childNames.includes( headerMeta.name ) || childNames.includes( detailsMeta.name );
}

export interface SummaryCollapse {
	clientId: string | null;
	collapsible: boolean;
	open: boolean;
	hasTrigger: boolean;
}

/** The layout's Order Summary collapse state. A copy of OrderSummaryCollapse::state(), which decides it for the storefront. */
export function useSummaryCollapse(): SummaryCollapse {
	return useSelect( ( select ) => {
		const { getBlocksByName, getBlockAttributes } = select( blockEditorStore );
		const clientId: string | null = getBlocksByName( metadata.name )[ 0 ] ?? null;
		const attributes = clientId ? getBlockAttributes( clientId ) : null;
		const hasTrigger = getBlocksByName( headerMeta.name ).length > 0 || getBlocksByName( toggleMeta.name ).length > 0;
		const collapsible = attributes?.collapsible === true && getBlocksByName( detailsMeta.name ).length > 0 && hasTrigger;

		return {
			clientId,
			collapsible,
			open: ! collapsible || attributes?.initialState === 'open',
			hasTrigger,
		};
	}, [] );
}
