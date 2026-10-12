import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import stepsMeta from '../steps/block.json';

/**
 * Visible label for a step title.
 *
 * An empty attribute stays empty in the saved block. Owner: Renderer\CheckoutProgress::step_title().
 */
export function stepTitle( title: string ): string {
	return title !== '' ? title : __( 'Step', 'yaycheckout' );
}

/**
 * Client ids of the layout's first Steps block's children, in document order; null without a Steps block.
 * The store's own order array, so typing elsewhere doesn't re-render the caller.
 * Mirrors LayoutMarkupReader::step_titles().
 */
export function useLayoutStepIds(): string[] | null {
	return useSelect( ( select ) => {
		const { getBlockOrder, getBlocksByName } = select( blockEditorStore );
		const stepsId = ( getBlocksByName( stepsMeta.name ) as string[] )[ 0 ];

		return stepsId ? getBlockOrder( stepsId ) : null;
	}, [] );
}
