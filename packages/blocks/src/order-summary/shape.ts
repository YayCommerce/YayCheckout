import detailsMeta from '../order-summary-details/block.json';
import headerMeta from '../order-summary-header/block.json';

/** Whether an Order Summary with these direct children prints a header and details instead of its heading. */
export function isNewShape( childNames: string[] ): boolean {
	return childNames.includes( headerMeta.name ) || childNames.includes( detailsMeta.name );
}
