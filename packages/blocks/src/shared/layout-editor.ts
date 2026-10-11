import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { editorData } from './editor-data';

/** Whether the post in the editor is a layout, not a page or post that the blocks may also load in. */
export function useIsLayoutEditor(): boolean {
	return useSelect( ( select ) => select( editorStore ).getCurrentPostType() === editorData.postType, [] );
}
