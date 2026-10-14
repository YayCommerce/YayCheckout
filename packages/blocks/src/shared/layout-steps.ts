import { store as blockEditorStore } from '@wordpress/block-editor';
import type { select as selectNow } from '@wordpress/data';
import stepsMeta from '../steps/block.json';

/**
 * The client id of the layout's first Steps block in the editor, or undefined without one. Mirrors
 * CheckoutStepsProgress::titles_in(), which reads the steps of the first Steps block.
 */
export function firstStepsBlock( registrySelect: typeof selectNow ): string | undefined {
	return ( registrySelect( blockEditorStore ).getBlocksByName( stepsMeta.name ) as string[] )[ 0 ];
}
