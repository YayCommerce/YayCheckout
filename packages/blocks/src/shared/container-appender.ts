import { InnerBlocks, store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

/**
 * A container's appender, by core Group and Column's rule: the outlined "+"
 * button only while the container is empty, otherwise core's own appender,
 * which core shows only while the container or a block inside it is selected.
 */
export function useContainerAppender( clientId: string ) {
	const isEmpty = useSelect(
		( select ) => select( blockEditorStore ).getBlockCount( clientId ) === 0,
		[ clientId ]
	);

	return isEmpty ? InnerBlocks.ButtonBlockAppender : undefined;
}
